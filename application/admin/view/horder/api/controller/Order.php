<?php
/**
 * Created by PhpStorm.
 * User: ljy
 * Date: 17/10/12
 * Time: 上午11:57
 */

namespace app\api\controller;

use think\Db;
use think\Request;
class Order extends Common
{
    private $cancel_time = '24*3600';

    protected function queryInfoByCar($member,$ids)
    {
        $list = Db::name('goods_shop_car')->alias('a')
            ->field('a.merchants_id,b.merchants_name,merchants_img')
            ->join('th_merchants b', 'a.merchants_id = b.member_id')
            ->where(['a.member_id' => $member['member_id'], 'a.is_valid' => '1', 'b.is_delete' => '0'])
            ->group('a.merchants_id')->select();
        $amount = '0';   //订单总金额
        $num = '0';     //订单总额
        foreach ($list as &$v) {
            $totalPrice = '0';     //商铺订单小记
            $totalNum = '0';    //商铺商品数
            $goods = Db::name('goods_shop_car')->alias('a')
                ->field('a.car_id,a.specification_id,b.goods_id,a.goods_name,a.goods_num,a.goods_img,specification_names,b.goods_origin_price,b.goods_pc_price,b.goods_now_price')
                ->join('th_goods b', 'a.goods_id = b.goods_id')
                ->where(['a.merchants_id' => $v['merchants_id'], 'a.member_id' => $member['member_id'], 'a.car_id' => ['in', $ids]])
                ->select();
            foreach ($goods as $key => $val) {
                if ($val['specification_id']) {
                    $specification = Db::name('goods_relation_specification')->where(['specification_id' => $val['specification_id']])->find();
                    if ($specification) {
                        $goods[$key]['goods_origin_price'] = $specification['specification_price'];
                        $goods[$key]['goods_now_price'] = $specification['specification_sale_price'];
                        $totalPrice += $specification['specification_sale_price'] * $val['goods_num'];
                    }
                } else {
                    $totalPrice += $val['goods_now_price'] * $val['goods_num'];
                }
                $totalNum += $val['goods_num'];
            }
            $v['totalPrice'] = sprintf('%.2f', $totalPrice);
            $v['totalNum'] = (string)$totalNum;
            $v['goods'] = $goods;
            $amount += $totalPrice;
            $num += $totalNum;
        }
        return array('list' => $list, 'amount' => sprintf('%.2f', $amount),'num'=>(string)$num,'deduct_integral_value'=>'0');
    }
    /**
     *确认订单（购物车）
     */
    public function confirmOrderInfo(){
        if (Request::instance()->isPost()) {
            $member = $this->checklogin();
            $ids = input('car_ids');
            if (!$ids) error("商品参数错误");
            $ids = explode(',', $ids);
            if (empty($ids)) error("商品参数错误");
            return success($this->queryInfoByCar($member,$ids));
        }
    }

    /**
     *确认订单（单件商品）
     */
    public function confirmGoodsInfo(){
        if (Request::instance()->isPost()) {
            $member = $this->checklogin();
            $goods_id = input('goods_id');
            $goods_num = input('goods_num');
            if (!$goods_id) error("商品参数错误");
            if (!$goods_num) error("商品数量错误");
            if (!$this->isSignlessInteger($goods_num)) error("商品数量错误");
            $specification_id = input('specification_id');
            $goods = Db::name('goods')->field('goods_id,goods_name,goods_img,goods_origin_price,goods_pc_price,goods_now_price,merchants_id')
                ->where(['goods_id' => $goods_id])->select();
            if (!$goods) error("商品参数错误");

            $list = Db::name('merchants')//店铺
            ->field('merchants_id,merchants_name,merchants_img')
                ->where(['member_id' => $goods[0]['merchants_id'], 'is_delete' => '0'])
                ->select();
            if (empty($list)) error("店铺错误");
            $totalPrice = '0';     //商铺订单小记
            foreach ($goods as $key => $val) {
                $goods[$key]['specification_names'] = '';
                if ($specification_id) {
                    $specification = Db::name('goods_relation_specification')->where(['specification_id' => $val['specification_id']])->find();
                    if ($specification) {
                        $goods[$key]['goods_origin_price'] = $specification['specification_price'];
                        $goods[$key]['goods_now_price'] = $specification['specification_sale_price'];
                        $totalPrice += $specification['specification_sale_price'] * $goods_num;
                        $goods[$key]['specification_names'] = $specification['specification_names'];
                    }
                } else {
                    $totalPrice += $val['goods_origin_price'] * $goods_num;
                }
                $goods[$key]['goods_num'] = $goods_num;
                $goods[$key]['specification_id'] = $specification_id;
            }
            foreach ($list as &$v) {
                $v['goods'] = $goods;
                $v['totalPrice'] = sprintf('%.2f', $totalPrice);
                $v['totalNum'] = (string)$goods_num;
            }

            success(['list' => $list, 'amount' => sprintf('%.2f', $totalPrice), 'num' => (string)$goods_num, 'deduct_integral_value' => '0']);
        }

    }

    /**
     *下单
     */
    public function insertMallOrder(){
        if (Request::instance()->isPost()) {
            $member = $this->checklogin();
            $json = input('json');
            if ($json) $json = json_decode($json, true);
            if (!$json['address_id']) error("收货地址错误");
            $address = Db::name('member_address')->where(['address_id' => $json['address_id']])->find();
            if (!$address) error("收货地址错误");
            if (empty($json['orderBeans'])) error("商品信息错误");
            $amount = '0';
            foreach ($json['orderBeans'] as &$v) {
                if (!$v['merchants_id']) error("商家信息错误");
                $totalPrice = '0';     //商铺订单小记
                foreach ($v['orderGoodsBeans'] as &$val) {
                    if (!$val['goods_id']) error("商品信息错误");
                    if (!$val['goods_num']) error("商品数量错误");
                    $goods = Db::name('goods')->where(['goods_id' => $val['goods_id']])->find();
                    if ($val['specification_id']) {
                        $specification = Db::name('goods_relation_specification')->where(['specification_id' => $val['specification_id']])->find();
                        if ($specification) {
                            $val['specification_ids'] = $specification['specification_ids'];
                            $val['specification_names'] = $specification['specification_names'];
                            $val['specification_stock'] = $specification['specification_stock'];
                            $val['specification_img'] = $specification['specification_img'];
                            $val['specification_price'] = $specification['specification_sale_price'];
                            $val['specification_id'] = $specification['specification_id'];
                            $totalPrice += $specification['specification_sale_price'] * $val['goods_num'];
                        }
                    } else {

                        $totalPrice += $goods['goods_now_price'] * $val['goods_num'];
                        $val['specification_ids'] = '';
                        $val['specification_names'] = '';
                        $val['specification_stock'] = $goods['goods_stock'];
                        $val['specification_img'] = '';
                        $val['specification_price'] = $goods['goods_now_price'];
                    }
                    $val['goods_name'] = $goods['goods_name'];
                    $val['goods_img'] = $goods['goods_img'];
                    $val['merchants_id'] = $v['merchants_id'];
                }
                $amount += $totalPrice;
                $v['totalPrice'] = $totalPrice;
            }

            if (!$amount) error("订单金额错误");

            if ($json['deduct_integral_value']) {
                if ($json['deduct_integral_value'] < $amount) {   //积分抵扣
                    $order['is_deduct_integral'] = 1;
                    $order['deduct_integral_value'] = $json['deduct_integral_value'];
                } else {
                    return error("无效积分抵扣");
                }
            }
            $order['order_no'] = rand(1000, 9999) . getMillisecond();
            $order['order_actual_price'] = $amount - $json['deduct_integral_value'];
            $order['order_total_price'] = $amount - $json['deduct_integral_value'];
            $order['goods_total_price'] = $amount;
            $order['member_id'] = $member['member_id'];
            $order['create_time'] = date("Y-m-d H:i:s", time());

            // 启动事务
            Db::startTrans();
            $result = Db::name('order')->insertGetId($order);
            if (!$result) {
                Db::rollback();
                error("下单失败");
            }

            foreach ($json['orderBeans'] as $v) {
                $order_merchants = array();
                $order_merchants['member_id'] = $member['member_id'];
                $order_merchants['merchants_id'] = $v['merchants_id'];
                $order_merchants['order_id'] = $result;
                $order_merchants['order_no'] = date("YmdHis", time()) . rand(10000, 99999);
                $order_merchants['address_id'] = $json['address_id'];
                $order_merchants['address_mobile'] = $address['address_mobile'];
                $order_merchants['address_name'] = $address['address_name'];
                $order_merchants['address_province'] = $address['address_province'];
                $order_merchants['address_city'] = $address['address_city'];
                $order_merchants['address_country'] = $address['address_country'];
                $order_merchants['address_detailed'] = $address['address_detailed'];
                $order_merchants['address_road'] = $address['address_road'];
                $order_merchants['address_zip_code'] = $address['address_zip_code'];
                $order_merchants['address_longitude'] = $address['address_longitude'];
                $order_merchants['address_latitude'] = $address['address_latitude'];
                $order_merchants['order_total_price'] = $v['totalPrice'];
                $order_merchants['order_actual_price'] = sprintf('%.2f', $order['order_actual_price'] * $v['totalPrice'] / $amount);//按商品原价平均拆分子订单总额
                if ($json['deduct_integral_value']) {
                    $order_merchants['deduct_integral_price'] = sprintf('%.2f', $json['deduct_integral_value'] * $v['totalPrice'] / $amount);//按商品原价平均拆分子订单积分抵扣总额
                    $order_merchants['is_deduct_integral'] = 1;
                    $order_merchants['deduct_integral_percent'] = sprintf('%.2f', $v['totalPrice'] / $amount * 100) . '%';
                }
                $order_merchants['goods_total_price'] = $v['totalPrice'];
                $order_merchants['order_remark'] = $v['order_remark'];
                $order_merchants['create_time'] = date("Y-m-d H:i:s", time());
                $order_merchants['cancel_time'] = time() + $this->cancel_time;
                $order_merchants['date'] = date("Y-m-d",time());
                $order_merchants_id = Db::name('order_merchants')->insertGetId($order_merchants);
                if (!$order_merchants_id) {
                    Db::rollback();
                    error("下单失败");
                }
                foreach ($v['orderGoodsBeans'] as &$val) {
                    $order_goods[] = [
                        'order_id' => $result,
                        'order_merchants_id' => $order_merchants_id,
                        'goods_name' => $val['goods_name'],
                        'goods_img' => $val['goods_img'],
                        'goods_id' => $val['goods_id'],
                        'goods_num' => $val['goods_num'],
                        'merchants_id' => $v['merchants_id'],
                        'specification_id' => $val['specification_id'],
                        'specification_ids' => $val['specification_ids'],
                        'specification_names' => $val['specification_names'],
                        'specification_stock' => $val['specification_stock'],
                        'specification_price' => $val['specification_price'],
                        'specification_img' => $val['specification_img'],
                        'create_time' => date("Y-m-d H:i:s", time())
                    ];
                }
            }
            $insertAll = Db::name('order_goods')->insertAll($order_goods);
            if (!$insertAll) {
                Db::rollback();
                error("下单失败");
            } else {
                Db::commit();
            }
            success(['order_id' => $result, 'order_no' => $order['order_no']]);
        }
    }

    //订单根据状态分类
    public function queryOrderByState(){
        if (Request::instance()->isPost()) {
            $member = $this->checklogin();
            Db::name('order_merchants')->where(['cancel_time'=>['gt',time()],'is_delete'=>'0'])->update(['is_delete'=>'1']);
            $p = input('p');
            empty($p) && $p = 1;
            $pageSize = input('pagesize');
            $pageSize ? $pageSize : $pageSize = 10;
            $order_state = input('order_state');
            !empty($order_state) ? $map['a.order_state'] = $order_state :$map['a.order_state'] = ['in',['wait_pay','wait_send','wait_receive','wait_assessment','end']];
            $map['a.member_id'] = $member['member_id'];
            $map['a.is_delete'] = '0';
            $map['a.order_type'] = 'goods';
            $list = Db::name('order_merchants')->alias('a')
                ->field('a.order_merchants_id,a.order_state,a.order_no,a.merchants_id,a.order_actual_price,b.merchants_name,b.merchants_img')
                ->join('th_merchants b', 'a.merchants_id = b.member_id')
                ->where($map)->order("a.create_time desc")->limit(($p - 1) * $pageSize, $pageSize)
                ->select();
            foreach ($list as &$v) {
                $totalNum = '0';
                $goods = Db::name('order_goods')->field('goods_id,goods_num,goods_name,goods_img,specification_id,specification_ids,specification_names,specification_price')
                    ->where(['order_merchants_id' => $v['order_merchants_id']])->select();
                foreach ($goods as &$val) {
                    if (!$val['specification_names']) {
                        $val['specification_names'] = '无';
                        $totalNum += $val['goods_num'];
                    }
                }
                $v['orderBeans'] = $goods;
                $v['totalNum'] = (string)$totalNum;
            }
            $count = Db::name('order_merchants')->alias('a')
                ->join('th_merchants b', 'a.merchants_id = b.member_id')
                ->where($map)->count();
            $page = ceil($count / $pageSize);
            success(['page' => $page, 'list' => $list]);
        }
    }

    //订单详情
    public function queryOrderView(){
        $member = $this->checklogin();
        $order_merchants_id = input('order_merchants_id');
        $map['a.order_merchants_id'] = $order_merchants_id;
        $map['a.member_id'] = $member['member_id'];
        $order = Db::name('order_merchants')->alias('a')
            ->field('a.order_merchants_id,a.order_id,a.order_state,a.order_no as pay_no,a.merchants_id,a.order_actual_price,b.merchants_name,b.merchants_img,b.merchants_name,a.create_time,a.pay_time,a.send_time,a.receive_time,
            a.order_remark,a.deduct_integral_value,c.order_no,a.address_mobile,a.address_name,a.address_province,a.address_city,address_country,a.address_longitude,a.address_latitude,a.address_detailed')
            ->join('th_merchants b', 'a.merchants_id = b.member_id')
            ->join('th_order c', 'a.order_id = c.order_id')
            ->where($map)->find();
        if($order){
            $totalNum = '0';
            $goods = Db::name('order_goods')->field('goods_id,goods_num,goods_name,goods_img,specification_id,specification_ids,specification_names,specification_price')
                ->where(['order_merchants_id' => $order['order_merchants_id']])->select();
            if($goods) {
                foreach ($goods as &$val) {
                    if (!$val['specification_names']) {
                        $val['specification_names'] = '无';
                    }
                    $totalNum += $val['goods_num'];
                }
                $order['orderBeans'] = $goods;

            }else{
                $order['orderBeans'] = [];
            }
            $order['totalNum'] = (string)$totalNum;
            $order['address']['address_mobile'] = $order['address_mobile'];
            $order['address']['address_name'] = $order['address_name'];
            $order['address']['address_province'] = $order['address_province'];
            $order['address']['address_city'] = $order['address_city'];
            $order['address']['address_country'] = $order['address_country'];
            $order['address']['address_longitude'] = $order['address_longitude'];
            $order['address']['address_latitude'] = $order['address_latitude'];
            $order['address']['address_detailed'] = $order['address_detailed'];
            success($order);
        }else{
            success((object)null);
        }
    }

    //取消订单
    public function cancelOrder(){
        $member = $this->checklogin();
        $order_merchants_id = input('order_merchants_id');
        if(!$order_merchants_id)        error("请选择订单");
        $where = [
            'order_merchants_id'=>$order_merchants_id,
            'member_id'     =>$member['member_id'],
            'order_state'   => 'wait_pay'
        ];
        $result = Db::name('order_merchants')->where($where)->update(['is_delete'=>'1','cancel_time'=>date("Y-m-d H:i:s",time())]);
        if($result){
            success("取消订单操作成功");
        }else{
            error("订单错误或状态不符合");
        }
    }

    //确认收货
    public function receiveOrder(){
        $member = $this->checklogin();
        $order_merchants_id = input('order_merchants_id');
        if(!$order_merchants_id)        error("请选择订单");
        $where = [
            'order_merchants_id'=>$order_merchants_id,
            'member_id'     =>$member['member_id'],
            'order_state'   => 'wait_receive'
        ];
        $result = Db::name('order_merchants')->where($where)->update(['order_state'=>'wait_assessment','receive_time'=>date("Y-m-d H:i:s",time())]);
        if($result){
            success("确认收货操作成功");
        }else{
            error("订单错误或状态不符合");
        }
    }

    //删除订单
    public function delOrder(){
        $member = $this->checklogin();
        $order_merchants_id = input('order_merchants_id');
        if(!$order_merchants_id)        error("请选择订单");
        $where = [
            'order_merchants_id'=>$order_merchants_id,
            'member_id'     =>$member['member_id'],
            'order_state'   => 'end'
        ];
        $result = Db::name('order_merchants')->where($where)->update(['is_delete'=>'1']);
        if($result){
            success("删除订单操作成功");
        }else{
            error("订单错误或状态不符合");
        }
    }

    public function cancelTime(){
        $member = $this->checklogin();
        $order_merchants_id = input('order_merchants_id');
        if(!$order_merchants_id)        error("请选择订单");
        $where = [
            'order_merchants_id'=>$order_merchants_id,
            'member_id'     =>$member['member_id'],
            'order_state'   =>'wait_pay'
        ];
        $result = Db::name('order_merchants')->where($where)->find();
        if($result){
            success($this->timediff(time(),$result['cancel_time']));
        }else{
            error("订单错误");
        }
    }

    public function test(){
        $string = array (
            'id' => 'evt_401171017091859864158902',
            'created' => 1508203139,
            'livemode' => true,
            'type' => 'charge.succeeded',
            'data' =>
                array (
                    'object' =>
                        array (
                            'id' => 'ch_fXfPe1SO8Sa9uz1yjLbrLS88',
                            'object' => 'charge',
                            'created' => 1508203121,
                            'livemode' => true,
                            'paid' => true,
                            'refunded' => false,
                            'reversed' => false,
                            'app' => 'app_OqLyzTz1WvDSO8Gm',
                            'channel' => 'wx',
                            'order_no' => '2017101618114592454Y1508203121',
                            'client_ip' => '101.90.126.213',
                            'amount' => 1,
                            'amount_settle' => 1,
                            'currency' => 'cny',
                            'subject' => '丑时商城订单',
                            'body' => 'Your Body',
                            'extra' =>
                                array (
                                    'bank_type' => 'CFT',
                                    'cash_fee' => '1',
                                    'is_subscribe' => 'N',
                                    'open_id' => 'oCEvxwg8Cu_P4bjhbyU9V_pay4YA',
                                ),
                            'time_paid' => 1508203138,
                            'time_expire' => 1508210321,
                            'time_settle' => NULL,
                            'transaction_no' => '4200000028201710178550862084',
                            'refunds' =>
                                array (
                                    'object' => 'list',
                                    'url' => '/v1/charges/ch_fXfPe1SO8Sa9uz1yjLbrLS88/refunds',
                                    'has_more' => false,
                                    'data' =>
                                        array (
                                        ),
                                ),
                            'amount_refunded' => 0,
                            'failure_code' => NULL,
                            'failure_msg' => NULL,
                            'metadata' =>
                                array (
                                ),
                            'credential' =>
                                array (
                                ),
                            'description' => NULL,
                        ),
                ),
            'object' => 'event',
            'request' => 'iar_vrzzPGa54KW5ejHeXT4mHW90',
            'pending_webhooks' => 0,
        );
        success($string);
    }


}
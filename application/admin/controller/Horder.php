<?php
/**
 * Created by PhpStorm.
 * User: ljy
 * Date: 17/10/30
 * Time: 上午11:09
 */

namespace app\admin\controller;

use think\Request;
use think\Session;
use think\Db;
use think\Validate;

class Horder extends Base
{

    protected function merchant(){
        $merchant = Db::name('merchants')->where(['is_delete'=>'0','apply_state'=>'2'])->select();
        return $merchant;
    }
    protected function get_order_count($map){
        $count = Db::name('order_merchants')->alias('a')
            ->join('th_member b','a.member_id = b.member_id')
            ->join('th_merchants c','a.merchants_id = c.member_id')
            ->where($map)->count();
        return $count;
    }

    protected function get_order_where(){
//        $merchant = $this->merchant;
//        $map['merchants_id'] = $merchant['member_id'];
        $merchant_id = input('merchants_id');
        !empty($merchant_id)  && $map['a.merchants_id'] = $merchant_id;
        $map['a.order_type'] = 'goods';
        $order_no = input('order_no');
        if($order_no)                  $map['a.order_no|a.address_mobile|b.username|b.phone'] = ['like','%'.$order_no.'%'];
        $start_time = input('start_time');
        $end_time = input('end_time');
        if($start_time){
            $start_time = urldecode($start_time);
        }
        if($start_time){
            $start_time = urldecode($start_time);
            $map['create_time'] = ['gt',$start_time];
        }
        if($end_time){
            $end_time = urldecode($end_time);
            $map['create_time'] = ['lt',$end_time];
        }
        $map['a.is_delete'] = '0';
        return $map;
    }

    protected function get_order($map,$num){
        $order_no = input('order_no');
        $order_state = input('order_state');
        $start_time = input('start_time');
        $end_time = input('end_time');
        $merchants_id = input('merchants_id');
        $start_time && $start_time = urldecode($start_time);
        $end_time && $end_time = urldecode($end_time);
        $list = Db::name('order_merchants')->alias('a')
            ->field('a.*,b.username,b.phone,c.merchants_name')
            ->join('th_member b','a.member_id = b.member_id')
            ->join('th_merchants c','a.merchants_id = c.member_id')
            ->where($map)->order("a.create_time desc")
            ->paginate($num,false,$config = ['query'=>array('order_no'=>$order_no,'order_state'=>$order_state,'start_time'=>$start_time,'end_time'=>$end_time,'merchants_id'=>$merchants_id)]);
        return $list;
    }

    protected function down_horder($map){

        $dat = Db::name('order_merchants')->alias('a')
            ->field('a.*,b.username,b.phone')
            ->join('th_member b','a.member_id = b.member_id')
            ->join('th_merchants c','a.merchants_id = c.member_id')
            ->where($map)->order("a.create_time desc")
            ->select();
        $str = '商品订单'.date('YmdHis');
        header('Content-Type: application/download');
        header("Content-type:application/vnd.ms-excel");
        header("Content-Disposition:attachment;filename={$str}.csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo "\xEF\xBB\xBF"."序号,订单号,订单收件人,收件人电话,订单总金额,实付金额,使用积分,成本价,下单会员,会员手机号,订单状态,商品信息,快递公司,快递单号,下单时间\n";
        foreach ($dat as $k=>$v){
            $goods = Db::name('order_goods')->where(['order_merchants_id'=>$v['order_merchants_id']])->select();
            foreach($goods as $key=>$val){
                $v['detail'] .= '商品名：'.$val['goods_name'].'，数量：'.$val['goods_num'].'，型号：'.$val['specification_names'];
            }
            switch($v['order_state']){
                case 'wait_pay' :
                    $v['state'] = '待支付';
                    break;
                case 'wait_send' :
                    $v['state'] = '待发货';
                    break;
                case 'wait_receive' :
                    $v['state'] = '待收货';
                    break;
                case 'wait_assessment' :
                    $v['state'] = '待评价';
                    break;
                case 'end' :
                    $v['state'] = '已完成';
                    break;
                case 'cancel' :
                    $v['state'] = '已取消';
                    break;
                case 'returns' :
                    $v['state'] = '已退款';
                    break;
            }
            switch ($v['order_type']){
                case 'goods':
                    $v['order_state'] = '正常下单';
                    break;
                case 'group':
                    $v['order_state'] = '团购下单';
                    break;
            }
            echo $k.","
                .$v["order_no"]."\t,"
                .$v["address_name"]."\t,"
                .$v["address_mobile"]."\t,"
                .$v["order_total_price"]."\t,"
                .$v["order_actual_price"]."\t,"
                .$v["deduct_integral_price"]."\t,"
                .$v["cost_price"]."\t,"
                .$v["username"]."\t,"
                .$v["phone"]."\t,"
                .$v["order_state"]."\t,"
                .$v["detail"]."\t,"
                .$v["logistics_name"]."\t,"
                .$v["logistics_no"]."\t,"
                .$v["create_time"]."\t,"
                ."\n";
        }

    }

    /**
     *@订单结算
     */
    public function settlement(){
        $order_merchant_id = input('order_merchant_id');
        if(!$order_merchant_id)         error("参数错误");
        $order = Db::name('order_merchants')->where(['order_merchants_id'=>$order_merchant_id])->find();
        if($order){
            if($order['settlement_state'] != 0 || $order['order_state'] != 'end'){
                error("结算状态错误");
            }
            $date = date("Y-m-d",time());
            $create_time = date("Y-m-d H:i:s",time());
            $merchants = Db::name('merchants')->where(['member_id'=>$order['merchants_id']])->find();
            Db::startTrans();
            if($merchants['tv_id']){
                if($merchants['tv_sell_scale']){    //电视台结算
                    $tv['order_merchants_id'] = $order['order_merchants_id'];
                    $tv['merchant_id'] = $merchants['tv_id'];
                    $tv['settlement_price'] = $order['order_actual_price']*$merchants['tv_sell_scale']/100;
                    $tv['order_price'] = $order['order_actual_price'];
                    $tv['ratio'] = $merchants['tv_sell_scale'];
                    $tv['create_time'] = $create_time;
                    $tv['date'] = $date;
                    $tv['type'] = '3';
                    $result = Db::name('order_settlement')->insert($tv);
                    if(!$result){
                        Db::rollback();
                        error("结算失败");
                    }
                }
            }
            //销售主播结算
            $seller_all = '0';
            $seller_goods = Db::name('order_goods')->where(['order_merchants_id'=>$order_merchant_id,'seller'=>['neq','']])->select();
            if($seller_goods){
                foreach ($seller_goods as $v){
                    if($v['specification_id']){
                        $sale_ratio = Db::name('goods_relation_specification')->where(['specification_id'=>$v['specification_id']])->value('sale_ratio');
                    }else{
                        $sale_ratio = Db::name('goods')->where(['goods_id'=>$v['goods_id']])->value('sale_ratio');
                    }
                    if($sale_ratio) {
                        $seller_money = sprintf('%.2f', $order['order_actual_price'] * (1 - $merchants['tv_sell_scale'] / 100 - $merchants['sell_scale'] / 100) * ($v['specification_price'] * $v['goods_num'] / $order['goods_total_price']) * $sale_ratio/100);
                        $seller[] = [
                            'order_merchants_id' => $order['order_merchants_id'],
                            'merchant_id' => $v['seller'],
                            'settlement_price' => $seller_money,
                            'order_price' => $order['order_actual_price'],
                            'ratio' => $sale_ratio,//销售主播比例是总订单除开平台和电视结余的百分之15
                            'create_time' => $create_time,
                            'type' => '2'
                        ];
                        $seller_all += $seller_money;
                        $result = Db::name('member')->where(['member_id' => $v['seller']])->setInc('amount', $seller_money);
                        if (!$result) {
                            Db::rollback();
                            error("结算失败");
                        }
                    }
                }
                if($seller){
                    $result = Db::name('order_settlement')->insertAll($seller);
                    if(!$result){
                        Db::rollback();
                        error("结算失败");
                    }
                }
            }

            //商家结算剩余

            $data['order_merchants_id'] = $order['order_merchants_id'];
            $data['merchant_id'] = $order['merchants_id'];
            $data['order_price'] = $order['order_actual_price'];
            $data['settlement_price'] = $order['order_actual_price']*(1-$merchants['sell_scale'])-$seller_all-$tv['settlement_price'];
            $data['ratio'] = sprintf('%.2f',$data['settlement_price']/$order['order_actual_price']*100);
            $data['create_time'] = $create_time;
            $data['date'] = $date;
            $result = Db::name('order_settlement')->insert($data);
            if($result){
                $a = Db::name('order_merchants')->where(['order_merchants_id'=>$order_merchant_id])->update(['settlement_state'=>'1']);
                $b = db::name('member')->where(['member_id'=>$order['merchants_id']])->setInc('amount',$data['settlement_price']);
                if($a && $b){
                    Db::commit();
                }else{
                    Db::rollback();
                    error("结算失败");
                }
                $work = '订单结算操作';
                $this->work_log($table='order_merchants',$record_id = $order['order_merchants_id'],'1',$work);
                success("操作成功");
            }else{
                Db::rollback();
                error("结算失败");
            }
        }else{
            error("参数错误");
        }
    }

    public function searchMerchant(){
        if(Request::instance()->isAjax()) {
            $name = input('name');
            $name && $map['merchants_name'] = ['like', '%' . $name . '%'];
            $map['is_delete'] = '0';
            $map['apply_state'] = '2';
            $merchants = Db::name('merchants')->where($map)->select();
            $type_list = "<option value=''>请选择商家店铺</option>";
            if ($merchants) {
                foreach ($merchants as $v) {
                    $type_list .= '<option value=' . $v['member_id'] . '>' . $v['merchants_name'] . '</option>';
                }
            }
            echo $type_list;
        }
    }

    /**
     *@今日新增订单
     */
    public function index(){
        $merchants_id = input('merchants_id');
        $today = date("Y-m-d 00:00:00",time());
        $map['a.create_time'] = ['gt',$today];
        $map['a.order_type'] = 'goods';
        !empty($merchants_id)   &&      $map['a.merchants_id'] = $merchants_id;
        $order_state = input('order_state');
        if($order_state)               $map['a.order_state'] = $order_state;
        $order_no = input('order_no');
        if($order_no)                  $map['a.order_no|a.address_name|a.a.address_mobile|b.username|b.phone'] = ['like','%'.$order_no.'%'];
        $map['a.is_delete'] = '0';
        $num = input('num');
        $num    ?   $num    :   $num = 10;

        $count = $this->get_order_count($map);

        $this->assign('nus',$num);

        $list  = $this->get_order($map);
        $page = $list->render();
        $merchant = $this->merchant();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page,'merchant'=>$merchant]);
        $act = input("get.act");
        if($act=="download"){
            $this->down_horder($map);
        }else{
            $url =$_SERVER['REQUEST_URI'];
            session('url',$url);
            return $this->fetch();
        }
    }

    /**
     *@全部订单
     */
    public function to_all_order(){
        $order_state = input('order_state');
        $map = $this->get_order_where();
        if($order_state)               $map['a.order_state'] = $order_state;
        $merchant_id = input('merchant_id');
        !empty($merchant_id)        &&      $map['a.merchants_id'] = $merchant_id;
        $num = input('num');
        $num    ?   $num    :   $num = 10;
        $this->assign('nus',$num);
        $count = $this->get_order_count($map);
        $list  = $this->get_order($map,$num);
        $page = $list->render();
        $merchant = $this->merchant();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page,'merchant'=>$merchant]);
        $act = input("act");
        if($act=="download"){
            $this->down_horder($map);
        }else{
            $url =$_SERVER['REQUEST_URI'];
            session('url',$url);
            return $this->fetch();
        }
    }

    /**
     *@售后订单
     */
    public function refund(){
        $merchant_id = input('merchants_id');
        !empty($merchant_id)  && $map['a.merchants_id'] = $merchant_id;
        $refund_type = input('refund_type');
        $refund_state = input('refund_state');
        !empty($refund_type)        &&      $map['refund_type'] = $refund_type;
        !empty($refund_state)        &&      $map['refund_state'] = $refund_state;
        $order_no = input('refund_no');
        if($order_no)                  $map['a.refund_no|b.username|b.phone'] = ['like','%'.$order_no.'%'];
        $map['a.is_delete'] = '0';
        $num = input('num');
        $num    ?   $num    :   $num = 10;

        $count = Db::name('order_refund')->alias('a')
            ->join('th_member b','a.member_id = b.member_id')
            ->join('th_merchants c','a.merchants_id = c.member_id')
            ->where($map)->count();
        $list = Db::name('order_refund')->alias('a')
            ->field('a.refund_id,a.refund_type,a.refund_no,a.refund_count,a.order_no,a.refund_reason,a.refund_desc,a.refund_img,a.refund_state,a.refund_price
                    ,b.username,b.phone,a.create_time,c.merchants_name')
            ->join('th_member b','a.member_id = b.member_id')
            ->join('th_merchants c','a.merchants_id = c.member_id')
            ->where($map)
            ->order('a.create_time desc')
            ->paginate($num,false,$config = ['query'=>array('order_no'=>$order_no,'refund_type'=>$refund_type,'refund_state'=>$refund_state)]);

        $this->assign('nus',$num);
        $page = $list->render();
        $merchant = $this->merchant();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page,'merchant'=>$merchant]);
        $act = input("act");
        if($act=="download"){
            $this->down_horder($map);
        }else{
            $url =$_SERVER['REQUEST_URI'];
            session('url',$url);
            return $this->fetch();
        }
    }

    /**
     *@订单详情
     */
    public function order_view(){
        $id = input('id');
        if(Request::instance()->isAjax()){
            $this->check_order_locker($id);
        }else {
            $map['a.order_no'] = $id;
            $re = Db::name('order_merchants')->alias('a')
                ->field('a.*,b.username,b.phone')
                ->join('th_member b','a.member_id = b.member_id')
                ->where($map)->find();
            $order = Db::name('order')->where(['order_id'=>$re['order_id']])->find();
            if (!empty($order['coupon_ids'])) {  //判断是否使用优惠券
                $coupon_ids = explode(',',$order['coupon_ids']);
                $coupon = Db::name('member_coupon')->alias('a')
                    ->field('title,value')
                    ->join('th_coupon b','a.coupon_id = b.coupon_id')
                    ->where(['a.id' => ['in', $coupon_ids]])->select();
            }
            $re['coupon'] = $coupon;

            /***
             * 商品信息
             */
            $goods = Db::name('order_goods')
                ->where(['order_merchants_id'=>$re['order_merchants_id']])->select();

            /*退换货信息*/
            $refund = Db::name('order_refund')->alias('a')
                    ->field('a.refund_type,a.refund_no,a.refund_count,a.refund_reason,a.refund_desc,a.refund_img,a.refund_state,a.refund_price
                    ,b.goods_name,b.specification_names')
                    ->join('th_order_goods b','a.order_goods_id = b.order_goods_id')
                    ->where(['a.order_merchants_id'=>$re['order_merchants_id'],'a.is_delete'=>'0'])
                    ->order('a.create_time desc')
                    ->select();


            /*日志*/
            $work_log = Db::name('WorkLog')
                ->field('title,intime,user_type,user_id')
                ->where(['table' => 'order_merchants', 'record_id' => $re['order_merchants_id']])->order("intime desc")->select();
            if(!empty($work_log)) {
                foreach ($work_log as &$v) {
                    if ($v['user_type'] == 1) {
                        $v['name'] = Db::name('system_member')->where(['id' => $v['user_id']])->value('username');
                    } else {
                        $v['name'] = Db::name('member')->where(['member_id' => $v['user_id']])->value('username');
                    }
                }
            }
            $url = session('url');
            $this->assign(['re' => $re, 'log' => $work_log,'goods'=>$goods,'refund'=>$refund,'url'=>$url]);

            return $this->fetch();
        }
    }

    /**
     *@售后详情
     */
    public function refund_view(){
        $id = input('id');
        if(Request::instance()->isAjax()){
            $this->check_order_locker($id);
        }else {
            $map['a.refund_no'] = $id;
            $re = Db::name('order_refund')->alias('a')
                ->field('a.refund_id,a.refund_type,a.refund_no,a.refund_count,a.order_no,a.refund_reason,a.refund_desc,a.refund_img,a.refund_state,a.refund_price
                    ,b.username,b.phone,a.create_time,a.order_goods_id,a.refund_actual_price')
                ->join('th_member b','a.member_id = b.member_id')
                ->where($map)->order('a.create_time desc')->find();

            $goods = Db::name('order_goods')->where(['order_goods_id'=>$re['order_goods_id']])->select();

            /*日志*/
            $work_log = Db::name('WorkLog')
                ->field('title,intime,user_type,user_id')
                ->where(['table' => 'order_refund', 'record_id' => $re['refund_id']])->order("intime desc")->select();
            if(!empty($work_log)) {
                foreach ($work_log as &$v) {
                    if ($v['user_type'] == 1) {
                        $v['name'] = Db::name('system_member')->where(['id' => $v['user_id']])->value('username');
                    } else {
                        $v['name'] = Db::name('member')->where(['member_id' => $v['user_id']])->value('username');
                    }
                }
            }
            $url = session('url');
            $this->assign(['re' => $re, 'log' => $work_log,'goods'=>$goods,'url'=>$url]);

            return $this->fetch();
        }
    }

    //快递
    public function express(){
        if(Request::instance()->isAjax()){
            $rule = [
                'logistics_name'      => 'require',
                'logistics_pinyin'   => 'require',
                'logistics_no'   => 'require|min:6',
                'order_no'   => 'require',
            ];
            $message = [
                'logistics_name.require' => '快递公司必须填写',
                'logistics_pinyin.require'     => '快递公司标识必须填写',
                'logistics_no.require'     => '物流单号必须填写',
                'logistics_no.min'     => '物流单号长度最小6位',
                'order_no.require'     => '订单单号必须填写',
            ];
            $data['order_no'] = input('order_no');
            $data['logistics_name'] = input('logistics_name');
            $data['logistics_pinyin'] = input('logistics_pinyin');
            $data['logistics_no'] = input('logistics_no');
            $validate = new Validate($rule,$message);
            $result = $validate->check($data);
            if(!$result)            error($validate->getError());
            $info = Db::name('order_merchants')->where(['order_no'=>$data['order_no']])->find();
            $result = Db::name('order_merchants')->where(['order_no'=>$data['order_no']])->update($data);
            if($result){
                $work = '修改了物流信息：';
                if($data['logistics_name'] != $info['logistics_name'])      $work .= '原物流公司:'.$info['logistics_name'].'；';
                if($data['logistics_no'] != $info['logistics_no'])      $work .= '原物流单号是:'.$info['logistics_no'].'；';
//                if($data['kuaidi_state'] != $info['kuaidi_state']){
//                    switch($info['kuaidi_state']){
//                        case 1:
//                            $work .= '原物流状态是:待发货；';
//                            break;
//                        case 2:
//                            $work .= '原物流状态是:已发货；';
//                            break;
//                        case 3:
//                            $work .= '原物流状态是:派送中；';
//                            break;
//                        case 4:
//                            $work .= '原物流状态是:已签收；';
//                            break;
//                    }
//                }
                $this->work_log($table='order_merchants',$record_id = $info['order_merchants_id'],'1',$work);

                success(['info'=>'保存物流信息成功']);
            }else{
                error("保存失败");
            }
        }else {
            $this->view->engine->layout(false);
            $order_no = input('order_no');
            $re = Db::name('order_merchants')->where(['order_no'=>$order_no])->find();
            /*物流*/
            $express_node = Db::name('ExpressNode')->select();
            $this->assign(['express_node'=>$express_node,'re'=>$re]);
            return $this->fetch();
        }
    }

    /**
     *@查找快递
     */
    public function getExpressNode(){
        $express = input('express');
        $re = Db::name("ExpressNode")->where(['express'=>$express])->find();
        if($re){
            echo $re['node'];
        }else{
            echo '';
        }
    }

    /**
     *@查找快递
     */
    public function getExpress(){
        $express = input('express');
        !empty($express)    &&  $map['express'] = ['like','%'.$express.'%'];
        $list = Db::name('ExpressNode')->where($map)->select();
        $code = '<option value="">请选择快递</option>';
        if($list){
            foreach($list as $k=>$v){
                $code .= "<option value=".$v['express'].">".$v['express']."</option>";
            }
        }
        echo $code;
    }

    /**
     *@下载
     */
    public function down_diy()
    {
        $order_no = I('order_no');
        $url = I('url');
        $arr = explode('?',$url);
        $new_url = $arr[0];
        $array = explode('.',$new_url);
        $str = $array[count($array) - 1];
        $result = httpcopy($url);
        $file = $result['fileName'];
        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . basename($file));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            ob_clean();
            flush();
            readfile($file);
            exit;
        }
    }


    /**
     *@退换货凭证图片
     */
    public function get_returns_img(){
        $id = I('id');
        $img = M('MallReturnsOrder')->where(['id'=>$id])->getField('img');
        $img = explode(',',$img);
        foreach($img as $k=>$v){
            $arr['data'][$k]['src'] = $v;
        }
        $arr['title'] = '';
        $arr['start'] = 0;
        echo json_encode($arr);
    }

    /**
     *@订单改价
     */
    public function change_paid(){
        if(Request::instance()->isAjax()){
            $order_no = input('order_no');
            $data['order_actual_price'] = input('order_actual_price');
            $data['cost_price'] = input('cost_price');
            $order = Db::name('order_merchants')->where(['order_no'=>$order_no])->find();
            if($order) {
                $result = Db::name('order_merchants')->where(['order_no' => $order_no])->update($data);
                if ($result) {
                    $work = '修改了订单价格：';
                    if($data['order_actual_price'] != $order['order_actual_price'])      $work .= '原订单实收金额:'.$order['order_actual_price'].'；';
                    if($data['cost_price'] != $order['cost_price'])      $work .= '原订单成本价是:'.$order['cost_price'].'；';
                    $this->work_log($table = 'order_merchants', $record_id = $order['order_merchants_id'], '1', $work = $work);
                    success('修改订单价格信息成功');
                } else {
                   error('修改订单价格失败');
                }
            }else{
                error("订单错误");
            }
        }
    }

    /**
     *@订单改价
     */
    public function change_refund_paid(){
        if(Request::instance()->isAjax()){
            $refund_no = input('refund_no');
            $data['refund_actual_price'] = input('refund_actual_price');
            $order = Db::name('order_refund')->where(['refund_no'=>$refund_no])->find();
            if($data['refund_actual_price']>$order['refund_price']){
                error("实际金额不能超出售后金额");
            }
            if($order) {
                $result = Db::name('order_refund')->where(['refund_no' => $refund_no])->update($data);
                if ($result) {
                    $work = '修改了售后价格：';
                    if($data['refund_actual_price'] != $order['refund_actual_price'])      $work .= '原售后实际金额:'.$order['refund_actual_price'].'；';
                    $this->work_log($table = 'order_refund', $record_id = $order['refund_id'], '1', $work = $work);
                    success('修改订单价格信息成功');
                } else {
                    error('修改订单价格失败');
                }
            }else{
                error("订单错误");
            }
        }
    }

    /**
     *@修改退换货订单
     */
    public function change_returns_state(){
        if(IS_POST){
            $id = I('id');
            $map['status'] = I('status');
            $map['uptime'] = date("Y-m-d H:i:s",time());
            $check = M('MallReturnsOrder')->where(['id'=>$id])->find();
            $result = M('MallReturnsOrder')->where(['id'=>$id])->save($map);
            if($result){
                if($check['type'] == 1){
                    $action = '退货';
                }else{
                    $action = '换货';
                }
                work_log($table='MallOrder',$record_id = $check['order_id'],'1',$work='修改了'.$action.'编号'.$check['number'].'的状态');
                echo json_encode(['status'=>'ok','info'=>$action."状态更改操作成功"]);
                die;
            }else{
                echo json_encode(['status'=>'error','info'=>"状态更改操作失败"]);
                die;
            }
        }
    }

    /**
     *@修改订单状态
     */
    public function change_order_status(){
        if(Request::instance()->isAjax()){
            $order_no = input('order_no');
            $state = input('state');
            $check = Db::name('order_merchants')->where(['order_no'=>$order_no])->find();
            if($check) {
                //$member = M('Member')->where(['member_id'=>$check['member_id']])->find();
                if ($check['order_state'] == $state) {
                    error('修改失败，订单状态未改变');
                }
                $time = date("Y-m-d H:i:s",time());
                switch ($state){
                    case 'cancel':
                        $data['cancel_time'] = $time;
                        break;
                    case 'wait_receive':
                        $data['send_time'] = $time;
                        break;
                    case 'wait_assessment':
                        $data['receive_time'] = $time;
                        break;
                }
                switch ($check['order_state']){
                    case 'cancel':
                        $tag = '取消';
                        break;
                    case 'wait_pay':
                        $tag = '待付款';
                        break;
                    case 'wait_send':
                        $tag = '待发货';
                        break;
                    case 'wait_receive':
                        $tag = '待收货';
                        break;
                    case 'wait_assessment':
                        $tag = '待评价';
                        break;
                    case 'end':
                        $tag = '已结束';
                        break;
                    case 'returns':
                        $tag = '已退款';
                        break;
                }
                $data['order_state'] = $state;
                $work = '修改了订单状态,';
                $work .='原订单状态是:'.$tag;
                $result = Db::name('order_merchants')->where(['order_merchants_id' => $check['order_merchants_id']])->update($data);
                if ($result) {
                    if($check['order_state'] == 'wait_receive'){
                        $this->set_message($check['member_id'],'订单已发货','2',$check['order_merchants_id']);
                    }
//                    if ($check['state'] != $data['state']) {
//                        switch ($data['state']) {
//                            case 3:
//                                $message = '你的订单已发货';
//                                set_message($check['mid'], $message, $check['id'], $check['type']);
//                                $this->send_SMS($check['phone'], $message);
//                                break;
//                            case 4 :
//                                if (in_array($check['type'], ['1', '2'])) {
//                                    $order_detail = M('MallOrderDetail')->where(['order_id' => $check['id']])->select();
//                                    foreach ($order_detail as $k => $v) {
//                                        M('Goods')->where(['goods_id' => $v['goods_id']])->setInc('sale_number', $v['number']);
//                                        if (!empty($v['kinds_id'])) {
//                                            $kinds = $v['kinds_id'];
//                                            M('GoodsStock')->where(['goods_id' => $v['goods_id'], 'kinds' => $kinds])->setInc('sale_number', $v['number']);
//                                        }
//                                    }
//
//                                }
//                                break;
//                            case 5:
//                                $message = '你的订单已完成';
//                                set_message($check['mid'], $message, $check['id'], $check['type']);
//                                break;
//                            case 6:
//                                $message = '你的订单已取消';
//                                set_message($check['mid'], $message, $check['id'], $check['type']);
//                                break;
//                        }
//                    }
                    if (!empty($member['openid'])) {
                        $weixin = new WechatAuth($this->system['appid'], $this->system['appsecret']);
                        $accessToken = S('globals_access_token');
                        if (empty($accessToken)) $accessToken = $weixin->getAccessToken();
                        $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $accessToken;
                        //$tmp = json_encode($tmp);
                        //$result = curl_post_json($url,$tmp);
                    }
                    $this->work_log($table = 'order_merchants', $record_id = $check['order_merchants_id'], '1', $work = $work);
                    success('修改订单状态成功');
                } else {
                    error('修改订单状态失败');
                }
            }else{
                error("订单无效");
            }
        }
    }

    /**
     *@删除订单
     */
    public function del_order(){
        if(Request::instance()->isAjax()) {
            $id = input('ids');
            $map['order_merchants_id'] = array('in', $id);
            $data['is_delete'] = 1;
            $result = Db::name('order_merchants')->where($map)->update($data);
            if ($result) {
                $id = explode(',',$id);
                if (is_array($id)) {
                    foreach ($id as $val) {
                        $this->work_log($table = 'order_merchants', $record_id = $val,'1', $work = '删除了订单记录');
                    }
                } else {
                    $this->work_log($table = 'order_merchants', $record_id = $id,'1', $work = '删除了订单记录');
                }
                success(['info' => '删除记录成功!', 'url' => session('url')]);
            } else {
                error('删除记录失败!');
            }
        }
    }

    /**
     *@删除售后
     */
    public function del_refund(){
        if(Request::instance()->isAjax()) {
            $id = input('ids');
            $map['refund_id'] = array('in', $id);
            $data['is_delete'] = 1;
            $result = Db::name('order_refund')->where($map)->update($data);
            if ($result) {
                $id = explode(',',$id);
                if (is_array($id)) {
                    foreach ($id as $val) {
                        $this->work_log($table = 'order_refund', $record_id = $val,'1', $work = '删除了售后订单记录');
                    }
                } else {
                    $this->work_log($table = 'order_refund', $record_id = $id,'1', $work = '删除了售后订单记录');
                }
                success(['info' => '删除记录成功!', 'url' => session('url')]);
            } else {
                error('删除记录失败!');
            }
        }
    }

    /**
     *@订单备注
     */
    public function beizhu(){
        if(Request::instance()->isAjax()){
            $id = input('id');
            $beizhu = input('beizhu');
            $check = Db::name('order_merchants')->where(['order_no'=>$id])->find();
            if($check['custom_remark'] == $beizhu){
                echo json_encode(['status' => "error", 'info' => '备注未做改变!']);
                die;
            }
            $result = Db::name('order_merchants')->where(['order_merchants_id'=>$check['order_merchants_id']])->update(['custom_remark'=>$beizhu]);
            if($result){
                $this->work_log($table = 'order_merchants', $record_id = $check['order_merchants_id'],'1', $work = '备注了订单,原备注信息是：'.$check['custom_remark']);
                echo json_encode(['status' => "ok", 'info' => '订单备注成功!']);
            }else{
                echo json_encode(['status' => "error", 'info' => '备注信息失败!']);
            }
        }
    }

    /**
     *@删除订单列表
     */
    public function is_del_order(){
        $map = [];
        $map['a.order_type'] = 'goods';
        $merchants_id = input('merchants_id');
        !empty($merchants_id) && $map['merchants_id'] = input('merchants_id');
        $order_no = input('order_no');
        if($order_no)                  $map['a.order_no|a.address_mobile|b.username|b.phone'] = ['like','%'.$order_no.'%'];
        $start_time = input('start_time');
        $end_time = input('end_time');
        if($start_time){
            $start_time = urldecode($start_time);
        }
        if($start_time){
            $start_time = urldecode($start_time);
            $map['create_time'] = ['gt',$start_time];
        }
        if($end_time){
            $end_time = urldecode($end_time);
            $map['create_time'] = ['lt',$end_time];
        }
        $map['a.is_delete'] = '1';
        $num = input('num');
        $num    ?   $num    :   $num = 10;
        $this->assign('nus',$num);
        $count = $this->get_order_count($map);
        $list  = $this->get_order($map,$num);
        $page = $list->render();
        $merchant = $this->merchant();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page,'merchant'=>$merchant]);
        $act = input("act");
        if($act=="download"){
            $this->down_horder($map);
        }else{
            $url =$_SERVER['REQUEST_URI'];
            session('url',$url);
            return $this->fetch();
        }

    }

    /**
     *@真实删除订单
     */
    public function del_order_true(){
        if(Request::instance()->isAjax()) {
            $id = input('ids');
            $data['order_merchants_id'] = array('in', $id);
            $result = Db::name('order_merchants')->where($data)->delete();
            if ($result) {
                success(array('info' => '删除记录成功!', 'url' => session('url')));
            } else {
                error('删除记录失败!');
            }
        }
    }

    /**
     *@恢复订单
     */
    public function recovery_order(){
        $id = input('ids');
        $data['order_merchants_id'] = array('in',$id);
        $result = Db::name('order_merchants')->where($data)->update(['is_delete'=>'0']);
        if($result){
            $id = explode(',',$id);
            if (is_array($id)) {
                foreach ($id as $val) {
                    $this->work_log($table = 'order_merchants', $record_id = $val,'1', $work = '恢复了订单记录');
                }
            } else {
                $this->work_log($table = 'order_merchants', $record_id = $id,'1', $work = '恢复了订单记录');
            }
            success(['info'=>'记录恢复成功!','url'=>session('url')]);
        }else{
            error('记录恢复失败!');
        }
    }

    /**
     *@待确认订单
     */
    public function to_be_confirm(){
        $map = [];
        $order_state = input('order_state');
        $map = $this->get_order_where();
        $order_state ? $map['order_state'] = $order_state : $map['order_state'] = 'wait_pay';
        $num = input('num');
        $num    ?   $num    :   $num = 10;
        $this->assign('nus',$num);
        $count = $this->get_order_count($map);
        $list  = $this->get_order($map,$num);
        $page = $list->render();
        $merchant = $this->merchant();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page,'merchant'=>$merchant]);
        $act = input("act");
        if($act=="download"){
            $this->down_horder($map);
        }else{
            $url =$_SERVER['REQUEST_URI'];
            session('url',$url);
            return $this->fetch();
        }
    }

    /**
     *@待支付订单
     */
    public function to_be_pay(){
        $map = [];
        $order_state = input('order_state');
        $map = $this->get_order_where();
        $order_state ? $map['order_state'] = $order_state : $map['order_state'] = 'wait_pay';
        $num = input('num');
        $num    ?   $num    :   $num = 10;
        $this->assign('nus',$num);
        $count = $this->get_order_count($map);
        $list  = $this->get_order($map,$num);
        $page = $list->render();
        $merchant = $this->merchant();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page,'merchant'=>$merchant]);
        $act = input("act");
        if($act=="download"){
            $this->down_horder($map);
        }else{
            $url =$_SERVER['REQUEST_URI'];
            session('url',$url);
            return $this->fetch();
        }
    }

    /**
     *@待发货
     */
    public function to_be_drawer(){
        $map = [];
        $order_state = input('order_state');
        $map = $this->get_order_where();
        $order_state ? $map['order_state'] = $order_state : $map['order_state'] = 'wait_send';
        $num = input('num');
        $num    ?   $num    :   $num = 10;
        $this->assign('nus',$num);
        $count = $this->get_order_count($map);
        $list  = $this->get_order($map,$num);
        $page = $list->render();
        $merchant = $this->merchant();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page,'merchant'=>$merchant]);
        $act = input("act");
        if($act=="download"){
            $this->down_horder($map);
        }else{
            $url =$_SERVER['REQUEST_URI'];
            session('url',$url);
            return $this->fetch();
        }
    }

    /**
     *@待收货订单
     */
    public function to_be_accept(){
        $map = [];
        $order_state = input('order_state');
        $map = $this->get_order_where();
        $order_state ? $map['order_state'] = $order_state : $map['order_state'] = 'wait_receive';
        $num = input('num');
        $num    ?   $num    :   $num = 10;
        $this->assign('nus',$num);
        $count = $this->get_order_count($map);
        $list  = $this->get_order($map,$num);
        $page = $list->render();
        $merchant = $this->merchant();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page,'merchant'=>$merchant]);
        $act = input("act");
        if($act=="download"){
            $this->down_horder($map);
        }else{
            $url =$_SERVER['REQUEST_URI'];
            session('url',$url);
            return $this->fetch();
        }
    }

    /**
     *@待评价订单
     */
    public function to_be_check(){
        $order_state = input('order_state');
        $map = $this->get_order_where();
        $order_state ? $map['order_state'] = $order_state : $map['order_state'] = 'wait_assessment';
        $num = input('num');
        $num    ?   $num    :   $num = 10;
        $this->assign('nus',$num);
        $count = $this->get_order_count($map);
        $list  = $this->get_order($map,$num);
        $page = $list->render();
        $merchant = $this->merchant();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page,'merchant'=>$merchant]);
        $act = input("act");
        if($act=="download"){
            $this->down_horder($map);
        }else{
            $url =$_SERVER['REQUEST_URI'];
            session('url',$url);
            return $this->fetch();
        }
    }

    /**
     *@已完成订单
     */
    public function complete(){
        $order_state = input('order_state');
        $map = $this->get_order_where();
        $order_state ? $map['order_state'] = $order_state : $map['order_state'] = 'end';
        $num = input('num');
        $num    ?   $num    :   $num = 10;
        $this->assign('nus',$num);
        $count = $this->get_order_count($map);
        $list  = $this->get_order($map,$num);
        $page = $list->render();
        $merchant = $this->merchant();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page,'merchant'=>$merchant]);
        $act = input("act");
        if($act=="download"){
            $this->down_horder($map);
        }else{
            $url =$_SERVER['REQUEST_URI'];
            session('url',$url);
            return $this->fetch();
        }
    }

    /**
     *@已取消订单
     */
    public function cancel_order(){
        $order_state = input('order_state');
        $map = $this->get_order_where();
        $order_state ? $map['order_state'] = $order_state : $map['order_state'] = 'cancel';
        $num = input('num');
        $num    ?   $num    :   $num = 10;
        $this->assign('nus',$num);
        $count = $this->get_order_count($map);
        $list  = $this->get_order($map,$num);
        $page = $list->render();
        $merchant = $this->merchant();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page,'merchant'=>$merchant]);
        $act = input("act");
        if($act=="download"){
            $this->down_horder($map);
        }else{
            $url =$_SERVER['REQUEST_URI'];
            session('url',$url);
            return $this->fetch();
        }
    }

    /**
     *@已退款订单
     */
    public function to_be_returns(){
        $order_state = input('order_state');
        $map = $this->get_order_where();
        $order_state ? $map['order_state'] = $order_state : $map['order_state'] = 'returns';
        $num = input('num');
        $num    ?   $num    :   $num = 10;
        $this->assign('nus',$num);
        $count = $this->get_order_count($map);
        $list  = $this->get_order($map,$num);
        $page = $list->render();
        $merchant = $this->merchant();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page,'merchant'=>$merchant]);
        $act = input("act");
        if($act=="download"){
            $this->down_horder($map);
        }else{
            $url =$_SERVER['REQUEST_URI'];
            session('url',$url);
            return $this->fetch();
        }
    }


    /**
     *@锁定订单
     */
    public function lock_order(){
        if(IS_POST){
            $id = I('id');
            $check = M('HotelOrder')->where(['order_id'=>$id])->find();
            $user = session('user');
            if($check['is_lock'] == '1'){
//                if($user['uname'] == 'admin'){
//                    echo json_encode(array('status'=>'error','info'=>'超级管理员不能锁定订单'));
//                    die;
//                }
                $result = M('HotelOrder')->where(['order_id'=>$id])->save(['is_lock'=>'2','locker_id'=>$user['id']]);
                $action = '锁定';
            }else{
                if($user['id'] == $check['locker_id'] || $user['uname'] == 'admin'){
                    $result = M('HotelOrder')->where(['order_id'=>$id])->save(['is_lock'=>'1','locker_id'=>'']);
                    $action = '解绑';
                }else{
                    echo json_encode(array('status'=>'error','info'=>'你没有权限解绑该订单'));
                    die;
                }
            }
            if($result){
                work_log($table='HotelOrder',$record_id = $id,'1',$work=$action.'了航班订单');
                echo json_encode(array('status'=>'ok','info'=>$action.'该订单成功'));
            }else{
                echo json_encode(array('status'=>'error','info'=>$action.'该订单失败'));
            }
        }
    }

    // 空方法
    public function _empty(){
        $this->view->engine->layout(false);
        return $this->fetch('common/error');
    }



}
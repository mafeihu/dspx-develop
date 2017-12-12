<?php
/**
 * Created by PhpStorm.
 * User: ljy
 * Date: 17/10/30
 * Time: 上午11:09
 */

namespace app\television\controller;

use think\Request;
use think\Session;
use think\Db;
use think\Validate;
class Earnings extends Base{
    protected $tv_id = '';
    public function _initialize()
    {
        parent::_initialize();
        $tv_info = Session::get("member");
        $this->tv_id = $tv_info['member_id'];
    }
    /**
     * 获取商户数组集
     */
    protected function get_merchants_id(){
        $tv_id = $this->tv_id;
        $map['a.is_delete'] = 0;
        $map["b.type"] = 2;
        $map["a.tv_id"] = $tv_id;
        $map["a.platform_type"] =1;
        $merchants_id =  DB::name("Merchants")
            ->alias('a')
            ->join('__MEMBER__ b','a.member_id = b.member_id')
            ->where($map)
            ->column("a.member_id");
        if(empty($merchants_id)){
            return [];
        }else{
            return $merchants_id;
        }
    }
    /**
     * 获取主播数组集
     */
    protected function get_anchor_id(){
        $tv_id = $this->tv_id;
        $merchants_id = $this->get_merchants_id();
        $map["b.type"] = 3;
        $map["a.tv_id"] = $tv_id;
        $anchor_id = DB::name("anchor_info")
            ->alias("a")
            ->join('__MEMBER__ b','a.anchor_id = b.member_id')
            ->where($map)
            ->column("a.anchor_id");
        $anchor_id = empty($anchor_id) ? [] : $anchor_id;
        $anchor_arr = array_merge($anchor_id,$merchants_id);
        return $anchor_arr;
    }
    /**
     * 获取销售订单总数
     */
    protected function get_order_count($map){
        $count = DB::name('order_settlement')
            ->alias('a')
            ->join('__MERCHANTS__ b','a.merchant_id = b.member_id')
            ->join("th_order_merchants d",'a.order_merchants_id = d.order_merchants_id')
            ->where($map)
            ->count();
        return $count;
    }
    /**
     *订单查询条件
     */
    protected function get_order_where($params){
        $television = $this->television;
        $map['a.merchant_id'] = ['in',$this->get_merchants_id()];
        $order_no = $params['order_no'];
        if($order_no)                  $map['b.merchants_name|b.contact_name|d.order_no'] = ['like','%'.$order_no.'%'];
        $start_time = $params['start_time'];
        $end_time = $params['end_time'];
        if($start_time){
            $start_time = urldecode($start_time);
        }
        if($start_time){
            $start_time = urldecode($start_time);
            $map['a.create_time'] = ['gt',$start_time];
        }
        if($end_time){
            $end_time = urldecode($end_time);
            $map['a.create_time'] = ['lt',$end_time];
        }
        $map['a.is_delete'] = '0';
        return $map;
    }
    /**
     * 直播打赏查询条件
     */
    public function get_live_where($params){
        $map['a.tv_id'] = $this->tv_id;
        $give_gift = $params['give_gift'];
        if($give_gift)                  $map['c.username|c.phone'] = ['like','%'.$give_gift.'%'];
        $give_gift = $params['give_gift'];
        $start_time = $params['start_time'];
        $end_time = $params['end_time'];
        if($start_time){
            $start_time = urldecode($start_time);
        }
        if($start_time){
            $start_time = urldecode($start_time);
            $map['a.create_time'] = ['gt',$start_time];
        }
        if($end_time){
            $end_time = urldecode($end_time);
            $map['a.create_time'] = ['lt',$end_time];
        }
        return $map;
    }
    /**
     * 直播打赏总条数
     */
    public function get_live_count($map){
        $count = DB::name('television_earnings')
            ->alias('a')
            ->join('th_give_gift b','a.give_gift_id = b.give_gift_id')
            ->join('__MEMBER__ c','a.anchor_id=c.member_id')
            ->where($map)
            ->count();
        return $count;
    }

    /**
     * 今日销售收益
     */
    public function today_sell(){
        $params = Request::instance()->param();
        $map  = $this->get_order_where($params);
        $today = date("Y-m-d 00:00:00",time());
        $map['a.create_time'] = ['gt',$today];
        $count = $this->get_order_count($map);
        $num  = $params['num'];
        if(empty($num)){
            $num = 10;
        }
        $list = DB::name('order_settlement')
            ->field('a.*,b.merchants_name,b.merchants_img,b.contact_name,d.order_no')
            ->alias('a')
            ->join('__MERCHANTS__ b','a.merchant_id = b.member_id')
            ->join("th_order_merchants d",'a.order_merchants_id = d.order_merchants_id')
            ->where($map)
            ->order("a.create_time desc")
            ->paginate($num,false,["query"=>$params]);
        $page = $list->render();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page]);
        return $this->fetch();
    }
    /**
     * 昨日销售收益
     */
    public function yesterday_sell(){
        $params = Request::instance()->param();
        $map  = $this->get_order_where($params);
        $yesterday = date("Y-m-d 00:00:00",strtotime("-1 day"));
        $today = date("Y-m-d 00:00:00",time());
        $map['a.create_time'] = ['between',[$yesterday,$today]];
        $count = $this->get_order_count($map);
        $num  = $params['num'];
        if(empty($num)){
            $num = 10;
        }
        $list = DB::name('order_settlement')
            ->field('a.*,b.merchants_name,b.merchants_img,b.contact_name,d.order_no')
            ->alias('a')
            ->join('__MERCHANTS__ b','a.merchant_id = b.member_id')
            ->join("th_order_merchants d",'a.order_merchants_id = d.order_merchants_id')
            ->where($map)
            ->order("a.create_time desc")
            ->paginate($num,false,["query"=>$params]);
        $page = $list->render();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page]);
        return $this->fetch();
    }
    /**
     *全部销售收益
     */
    public function all_sell(){
        $params = Request::instance()->param();
        $map  = $this->get_order_where($params);
        $count = $this->get_order_count($map);
        $num  = $params['num'];
        if(empty($num)){
            $num = 10;
        }
        $list = DB::name('order_settlement')
            ->field('a.*,b.merchants_name,b.merchants_img,b.contact_name,d.order_no')
            ->alias('a')
            ->join('__MERCHANTS__ b','a.merchant_id = b.member_id')
            ->join("th_order_merchants d",'a.order_merchants_id = d.order_merchants_id')
            ->where($map)
            ->order("a.create_time desc")
            ->paginate($num,false,["query"=>$params]);
        $page = $list->render();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page]);
        return $this->fetch();
    }
    /**
     * 今日直播收益
     */
    public function today_live(){
        $params = Request::instance()->param();
        $map = $this->get_live_where($params);
        $today = date("Y-m-d 00:00:00",time());
        $map['a.create_time'] = ['gt',$today];
        $count = $this->get_live_count($map);
        $num  = $params['num'];
        if(empty($num)){
            $num = 10;
        }
        $list = DB::name('television_earnings')
             ->alias('a')
             ->field('a.*,b.*,c.header_img,c.phone,c.username')
             ->join('th_give_gift b','a.give_gift_id = b.give_gift_id')
             ->join('__MEMBER__ c','a.anchor_id=c.member_id')
             ->where($map)
             ->order("a.create_time desc")
             ->paginate($num,false,["query"=>$params]);
        $page = $list->render();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page]);
        return $this->fetch();
    }
    /**
     *昨天直播收益
     */
    public function yesterday_live(){
        $params = Request::instance()->param();
        $map = $this->get_live_where($params);
        $yesterday = date("Y-m-d 00:00:00",strtotime("-1 day"));
        $today = date("Y-m-d 00:00:00",time());
        $map['a.create_time'] = ['between',[$yesterday,$today]];
        $count = $this->get_live_count($map);
        $num  = $params['num'];
        if(empty($num)){
            $num = 10;
        }
        $list = DB::name('television_earnings')
            ->alias('a')
            ->field('a.*,b.*,c.header_img,c.phone,c.username')
            ->join('th_give_gift b','a.give_gift_id = b.give_gift_id')
            ->join('__MEMBER__ c','a.anchor_id=c.member_id')
            ->where($map)
            ->order("a.create_time desc")
            ->paginate($num,false,["query"=>$params]);
        $page = $list->render();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page]);
        return $this->fetch();
    }
    /**
     * 全部直播收益
     */
    public function all_live(){
        $params = Request::instance()->param();
        $map = $this->get_live_where($params);
        $count = $this->get_live_count($map);
        $num  = $params['num'];
        if(empty($num)){
            $num = 10;
        }
        $list = DB::name('television_earnings')
            ->alias('a')
            ->field('a.*,b.*,c.header_img,c.phone,c.username')
            ->join('th_give_gift b','a.give_gift_id = b.give_gift_id')
            ->join('__MEMBER__ c','a.anchor_id=c.member_id')
            ->where($map)
            ->order("a.create_time desc")
            ->paginate($num,false,["query"=>$params]);
        $page = $list->render();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page]);
        return $this->fetch();
        $this->assign(['list'=>$list,'count'=>$count,'page'=>$page]);
        return $this->fetch();
    }
}
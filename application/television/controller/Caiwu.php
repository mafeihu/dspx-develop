<?php
namespace app\television\controller;
use think\controller;
use think\Db;
use think\Request;
use think\Validate;
use think\session;
class Caiwu extends Base{
    public function index(){
        return $this->fetch();
    }
    /**
     * 直播收益财务报表
     * @return mixed
     */
    public function anchor(){
        $member =session::get("member");
        $map=[];
        $params = Request::instance()->param();
        $username = $params["username"];
        if($username)   $map['b.username|b.phone|d.title'] = ['like','%'.$username.'%'];
        if($params["member_type"]) $map["a.member_type"] = $params["member_type"];
        $start_time = $params["start_time"];
        $end_time = $params["end_time"];
        //时间
        if($start_time){
            $map["a.date"] = ["gt",urldecode($start_time)];
        }
        if($end_time){
            $map["a.date"] = ['lt',urldecode($end_time)];
        }
        $num  = input('num');
        if(empty($num)){
            $num=10;
        }
        $count = DB::name("give_gift")
                ->alias('a')
                ->join("__MEMBER__ b","a.user_id2 = b.member_id","LEFT")
                ->join("__GIFT__ c","a.gift_id = c.gift_id")
                ->join("__LIVE__ d","a.live_id = d.live_id")
                ->where(["a.tv_id"=>$member["member_id"]])
                ->where($map)
                ->count();
        $list = DB::name("give_gift")
                ->field("a.*,b.username,b.header_img,b.phone,c.*,d.title")
                ->alias('a')
                ->join("__MEMBER__ b","a.user_id2 = b.member_id","LEFT")
                ->join("__GIFT__ c","a.gift_id = c.gift_id",'LEFT')
                ->join("__LIVE__ d","a.live_id = d.live_id")
                ->where(["a.tv_id"=>$member["member_id"]])
                ->where($map)
                ->order("a.intime desc")
                ->paginate($num,false,["query"=>$params]);
        $system = DB::name("system")->where(["id"=>1])->find();
        $change_scale = $change_scale = $system["convert_scale1"]/$system["convert_scale2"];
        $list->toArray();
        foreach ($list as $k=>$v){
            $platform_scale = explode(',',$v["dashang_scale"])[0]/100;
            $anchor_scale = explode(",",$v["dashang_scale"])[1]/100;
            $anchor_amount = $v["price"]*$change_scale*$platform_scale*$anchor_scale;
            $platform_amount = $v["price"]*$change_scale*$platform_scale*(1-$anchor_scale);
            $data = array();
            $data = $v;
            $data['anchor_amount'] = $anchor_amount;
            $data['platform_amount'] = $platform_amount;
            $list->offsetSet($k,$data);
        }
       $this->assign("count",$count);
       $this->assign("list",$list);
       return $this->fetch();
    }
    /**
     * 商户销售报表
     * @return mixed
     */
    public function  merchants(){
        //获取电视台id
        $member =session::get("member");
        $tv_id = $member["member_id"];
        //获取电视台下的商户
        $merchants =array("tv_id"=>$tv_id,"platform_type"=>1);
        $merchants_id = DB::name("Merchants")->where($merchants)->column("member_id");
        if(empty($num)){
            $num =10;
        }
        $params = Request::instance()->param();
        $order_no = $params["order_no"];
        if($order_no)                  $map['a.order_no|c.username|c.phone|b.merchants_name'] = ['like','%'.$order_no.'%'];
        //获取相应对应商户的支付订单信息
        if($params["order_state"]){
            $map["order_state"] = $params["order_state"];
        }else{
            $map['order_state'] = ['in','wait_send','wait_receive','wait_assessment','end'];
        }
        //订单时间
        $start_time = $params["start_time"];
        $end_time = $params["end_time"];
        if($start_time){
            $map["a.create_time"] = ["gt",urldecode($start_time)];
        }
        if($end_time){
            $map["a.create_time"] = ['lt',urldecode($end_time)];
        }
        $map["a.merchants_id"] = ['in',$merchants_id];
        if(!empty($merchants_id)){
            $count = DB::name("order_merchants")
                ->alias("a")
                ->join("__MERCHANTS__ b","a.merchants_id=b.member_id")
                ->join("__MEMBER__ c","a.member_id = c.member_id")
                ->where($map)
                ->count();
            $list = DB::name("order_merchants")
                ->alias("a")
                ->field("a.*,b.merchants_img,b.merchants_name,b.contact_name,b.contact_mobile,c.phone,c.username")
                ->join("__MERCHANTS__ b","a.merchants_id=b.member_id")
                ->join("__MEMBER__ c","a.member_id = c.member_id")
                ->where($map)
                ->paginate($num,false,["query"=>$params]);
            $page = $list->render();
            $this->assign(["count"=>$count,"list"=>$list,'page'=>$page]);
        }else{
                $this->assign(["count"=>0,"list"=>[]]);
        }
        return $this->fetch();
    }
    /**
     * 编辑页面
     */
    public function edit_anchor(){

    }
}
<?php
namespace app\admin\controller;
use think\Controller;
use think\View;
use think\Db;
use \think\Session;
use \think\Request;
class Finance extends Base{
    public function index(){
        /**
         *充值记录
         */
        $map = [];
        !empty($_GET['pay_type']) && $map['a.pay_type'] = ['like','%'.input('pay_type').'%'];
        !empty($_GET['username']) && $map['b.username|b.phone|a.pay_number'] = ['like','%'.input('username').'%'];
        if(!empty($_GET['start_time'])) $start_time = date("Y-m-d H:i:s",strtotime(input('start_time'))); else $start_time = 0;
        if(!empty($_GET['end_time']))   $end_time = date("Y-m-d H:i:s",strtotime(input('end_time'))); else $end_time = date("Y-m-d H:i:s",time());
        $map['a.intime'] = ['between',[$start_time,$end_time]];
        $map['a.pay_state'] = '2';
        $map['a.is_del'] = '1';
        if (empty($num)){
            $num = 10;
        }
        $this->assign('nus',$num);
        $count = DB::name('Recharge')->alias('a')
            ->join("__MEMBER__ b","a.member_id = b.member_id",'LEFT')
            ->where($map)->count();
        $list  = DB::name('Recharge')->alias('a')
            ->field('a.recharge_record_id,a.pay_number,a.amount,a.pay_type,b.username,b.phone,a.intime,b.grade')
            ->join("__MEMBER__ b","a.member_id = b.member_id","LEFT")
            ->where($map)
            ->order("a.intime desc")
            ->paginate($num,false,['query' => Request::instance()->param()]);
        foreach($list as $key=>$val){
            $list[$key]['grade'] = DB::name('Grade')->where(['grade_id'=>$val['grade']])->value('name');
        }
        $sum = DB::name("Recharge")->alias('a')
            ->join("__MEMBER__ b","a.member_id = b.member_id","LEFT")
            ->where($map)
            ->sum('a.amount');
        $this->assign(['list'=>$list,'count'=>$count,'sum'=>$sum]);
        $act = input("get.act");
        if($act == 'download'){
            $dat = DB::name('Recharge')->alias('a')
                ->field('a.recharge_record_id,a.pay_number,a.amount,a.pay_type,b.username,b.phone,a.intime,b.grade')
                ->join("__MEMBER__ b","a.member_id = b.member_id","LEFT")
                ->where($map)
                ->order("a.intime desc")
                ->select();
            $str = '充值记录表格'.date('YmdHis');
            header('Content-Type: application/download');
            header("Content-type:application/vnd.ms-excel");
            header("Content-Disposition:attachment;filename={$str}.csv");
            header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
            header('Expires:0');
            header('Pragma:public');
            echo "\xEF\xBB\xBF"."序号,充值会员,订单号,充值账号,充值金额,支付类型,充值时间\n";
            foreach($dat as $key=>$val){
                echo $key.","
                    .$val["username"]."\t,"
                    .$val["pay_number"]."\t,"
                    .$val["phone"]."\t,"
                    .$val["amount"]."\t,"
                    .$val["pay_type"]."\t,"
                    .$val["intime"]."\t,"
                    ."\n";
            }
        }else{
            $url = $_SERVER['REQUEST_URI'];
            session('url',$url);
            return $this->fetch();
        }
    }
    /**
     * @提现记录
     */
    public function withdraw(){
        $map=[];
        !empty($_GET['status']) && $map['a.status'] = input('status');
        !empty($_GET['username']) && $map['b.username|b.phone|a.relname|a.withdraw_way'] = ['like','%'.input('username').'%'];
        if(!empty($_GET['start_time'])) $start_time = strtotime(input('start_time')); else $start_time = 0;
        if(!empty($_GET['end_time']))   $end_time = strtotime(input('end_time')); else $end_time = time();
        $map['a.intime'] = ['between',[$start_time,$end_time]];
        if (empty($num)){
            $num = 10;
        }
        $count = DB::name("Withdraw")->alias('a')
            ->join("__MEMBER__ b","a.user_id = b.member_id")
            ->where($map)->count(); // 查询满足要求的总记录数
        $sum = DB::name("Withdraw")->alias('a')
            ->join("__MEMBER__ b","a.user_id = b.member_id")
            ->where($map)->sum("money");
        $data=DB::name("Withdraw")->alias('a')
            ->field('a.*,b.username,b.phone,b.member_id as uid')
            ->join("__MEMBER__ b","a.user_id = b.member_id")
            ->where($map)
            ->order('a.intime desc')
            ->paginate($num,false);
        $this->assign(['list'=>$data,'count'=>$count,"sum"=>$sum]);
        $act = input("get.act");
        if($act == 'download'){
            $dat=DB::name("Withdraw")->alias('a')
                ->field('a.*,b.username,b.phone,b.member_id as uid')
                ->join("__MEMBER__ b","a.user_id = b.member_id")
                ->where($map)
                ->order('a.intime desc')
                ->select();
            $str = '提现统计表格'.date('YmdHis');
            header('Content-Type: application/download');
            header("Content-type:application/vnd.ms-excel");
            header("Content-Disposition:attachment;filename={$str}.csv");
            header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
            header('Expires:0');
            header('Pragma:public');
            echo "\xEF\xBB\xBF"."序号,名称,手机号,提现龙票,金额,账户名,账户,类型,状态,申请时间,返现时间\n";
            foreach($dat as $key=>$val){
                switch($val['pay_type']){
                    case 1:
                        $val['withdraw_type'] = '支付宝';
                        break;
                    case 2:
                        $val['withdraw_type'] = '银行卡';
                        break;
                }
                switch($val['status']){
                    case 1:
                        $val['status'] = '申请中';
                        break;
                    case 2:
                        $val['status'] = '冻结中';
                        break;
                    case 3:
                        $val['status'] = '已返现';
                        break;
                }
                echo $key.","
                    .$val["username"]."\t,"
                    .$val["phone"]."\t,"
                    .$val["k"]."\t,"
                    .$val["money"]."\t,"
                    .$val["relname"]."\t,"
                    .$val["withdraw_way"]."\t,"
                    .$val["withdraw_type"]."\t,"
                    .$val["status"]."\t,"
                    .date('Y-m-d H:i:s',$val["intime"])."\t,"
                    .date('Y-m-d H:i:s',$val["cash_time"])."\t,"
                    ."\n";
            }
        }else{
            $url = $_SERVER['REQUEST_URI'];
            session('url',$url);
            return $this->fetch();
        }
    }
    /**
     *编辑审核信息
     */
    public function edit_withdraw(){
        $params = Request::instance()->param();
        if(Request::instance()->isPost()){
            $status = input('status');
            $result = DB::name('Withdraw')->where(['withdraw_id'=>$params["id"]])->update(['status'=>$status,'uptime'=>time(),'cash_time'=>time()]);
            if ($result) {
                echo json_encode(['status' => "ok", 'info' => '修改记录成功!', 'url' => session('url')]);
                die;
            } else {
                echo json_encode(['status' => "error", 'info' => '修改记录失败!']);
                die;
            }
        }else{
            $this->view->engine->layout(false);
            $id = $params["id"];
            $re = DB::name("Withdraw")->alias('a')
                ->field('a.*,b.username,b.phone')
                ->join("__MEMBER__ b", "a.user_id = b.member_id")
                ->where(['a.withdraw_id'=>$id])
                ->find();
            $this->assign('re',$re);
            return $this->fetch();
        }
    }
    /**
     *@真实删除订单
     */
    public function del_recharge(){
        if(Request::instance()->isPost()) {
            $id = input('ids');
            $result = DB::name('Recharge')->where('recharge_record_id','in',$id)->update(['is_del'=>2]);
            if ($result) {
                echo json_encode(['status' => "ok", 'info' => '删除记录成功!', 'url' => session('url')]);
            } else {
                echo json_encode(['status' => "error", 'info' => '删除记录失败!']);
            }
        }
    }
    /**
     * 送礼记录
     */
    public function give_gift(){
        !empty($_GET['username']) && $map['b.username|b.phone|c.title'] = ['like','%'.input('username').'%'];
        if(!empty($_GET['start_time'])) $start_time = strtotime(input('start_time')); else $start_time = 0;
        if(!empty($_GET['end_time']))   $end_time = strtotime(input('end_time')); else $end_time = time();
        $map['a.intime'] = ['between',[$start_time,$end_time]];
        $params = Request::instance()->param();
        $system = DB::name("system")->where(["id"=>1])->find();
        $withdraw_scale = $system["convert_scale4"]/$system["convert_scale3"];
        $num = input("num");
        if(empty($num));$num=10;
        $count= DB::name("give_gift")
            ->alias("a")
            ->join("__MEMBER__ b","a.user_id2 = b.member_id","left")
            ->join("__LIVE__ c","a.live_id = c.live_id","left")
            ->where($map)
            ->count();
        $list = DB::name("give_gift")
            ->alias("a")
            ->field("a.*,b.username,b.phone,b.header_img,c.play_img,c.title")
            ->join("__MEMBER__ b","a.user_id2 = b.member_id","left")
            ->join("__LIVE__ c","a.live_id = c.live_id","left")
            ->order("a.intime desc")
            ->where($map)
            ->paginate($num,false,["query"=>$params]);
        $list->toArray();
        foreach ($list as $k=>$v){
            $data = array();
            $data = $v;
            $data['case_money'] = $v["e_ticket"]*$withdraw_scale;
            $list->offsetSet($k,$data);
        }
        $page = $list->render();
        $this->assign(["count"=>$count,"list"=>$list,"page"=>$page]);
        return $this->fetch();
    }
}
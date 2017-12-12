<?php
/**
 * Created by PhpStorm.
 * User: ljy
 * Date: 17/9/26
 * Time: 下午2:14
 */

namespace app\merchant\controller;

use think\Db;
use think\Request;
class Index extends Base
{
    public function index()
    {
        $merchant = $this->merchant;
        $merchants = Db::name('merchants')->where(['member_id'=>$merchant['member_id']])->find();
        $goods_count1 = Db::name('goods')->where(['merchants_id'=>$merchant['member_id'],'is_delete'=>'0'])->count();//商品总数
        $goods_count2 = Db::name('goods')->where(['merchants_id'=>$merchant['member_id'],'is_delete'=>'0','is_review'=>'1','goods_state'=>'2'])->count();//上架总数
        $order_count1 = Db::name('order_merchants')->where(['merchants_id'=>$merchant['member_id']])->count();//订单总数
        $order_count2 = Db::name('order_merchants')->where(['merchants_id'=>$merchant['member_id'],'order_state'=>['not in',['cancel','wait_pay','returns']]])->sum('order_actual_price');//订单金额
        $order_count3 = Db::name('order_settlement')->where(['merchant_id'=>$merchant['member_id'],'is_delete'=>'0'])->sum('settlement_price');//结算金额
        $total_income = DB::name("give_gift")->where(["user_id2"=>$merchant["member_id"]])->sum('jewel');   //直播收益
        $total_withdraw = DB::name("withdraw")->where(["user_id"=>$merchant["member_id"],"status"=>3])->sum('money');  //提现金额
        $this->assign(['goods_count1'=>$goods_count1,'goods_count2'=>$goods_count2,'order_count'=>$order_count1,'order_count2'=>$order_count2,
            'order_count3'=>$order_count3,'merchants'=>$merchants,'total_withdraw'=>$total_withdraw,'total_income'=>$total_income]);
        $fans_count = Db::name('follow')->where(['user_id2'=>$merchant['member_id'],'is_delete'=>'0'])->count();     //粉丝数
        $follow_count = Db::name('follow')->where(['user_id'=>$merchant['member_id'],'is_delete'=>'0'])->count();  //关注数
        $live_count = Db::name('live')->where(['user_id'=>$merchant['member_id']])->count();//直播次数
        $this->assign(['fans_count'=>$fans_count,'follow_count'=>$follow_count,'live_count'=>$live_count,'merchant'=>$merchant]);
        return $this->fetch();
    }

    public function day_code()
    {
        $merchant = $this->merchant;
        $time = input('time');
        $time = '2017-10-19';
        //$time ?  $map['pay_time'] = ['between time',[$time,date("Y-m-d H:i:s",strtotime($time)+24*3600)]] : $map['pay_time'] = ['gt',date("Y-m-d 00:00:00",time())];
        $map['merchants_id'] = $merchant['member_id'];
        $map['order_state'] = ['neq', 'wait_pay,cancel'];
        $time ? $code = strtotime($time) : $code = time();
        $day = date("Y-m-d", $code);

        $stamp1 = strtotime($day);
        $stamp2 = strtotime("+1 day", $stamp1);
        $a = [];        //活跃数据
        $first = date("H:i", $stamp1);
        //$b = [$first];        //日期数据
        $b = [];        //日期数据
        for ($i = 0; $i < 24; $i++) {
            $start = strtotime("+{$i} hour", $stamp1);
            $end = $i + 1;
            $next = $i . '-' . $end . '时';
            $end = strtotime("+{$end} hour", $stamp1);
            $map['pay_time'] = ['between', [date("Y-m-d H:i:s", $start), date("Y-m-d H:i:s", $end)]];
            $a1 = $summit_actual_price = Db::name('order_merchants')->where($map)->sum('order_actual_price');;
            $a1 = (float)sprintf('%.2f', $a1);
            array_push($a, $a1);
            //$next = date("H:i",$end);
            array_push($b, $next);
        }

        /*        $c = M('IntoApp')->where(['intime'=>['between',[$stamp1,$stamp2]]])->count(); //当月总活跃*/
        success(['a' => $a, 'b' => $b]);
    }

    public function month_code()
    {
        $merchant = $this->merchant;
        $code = input('code');
        !empty($code) ? $code = strtotime($code) : $code = time();
        $map['order_state'] = ['neq', 'wait_pay,cancel'];
        $map['merchants_id'] = $merchant['member_id'];
        $month = date("Y-m", $code);
        $stamp1 = strtotime($month);
        $stamp2 = strtotime("+1 month", $stamp1);
        $date_count = ($stamp2 - $stamp1) / 24 / 3600;
        $a = [];        //活跃数据
        $first = date("d", $stamp1);
        $b = [$first];        //日期数据
        for ($i = 0; $i < $date_count; $i++) {
            $start = strtotime("+{$i} day", $stamp1);
            $end = $i + 1;
            $end = strtotime("+{$end} day", $stamp1);
            $map['pay_time'] = ['between', [date("Y-m-d H:i:s", $start), date("Y-m-d H:i:s", $end)]];
            $a1 = $summit_actual_price = Db::name('order_merchants')->where($map)->sum('order_actual_price');;
            $a1 = (float)sprintf('%.2f', $a1);
            array_push($a, $a1);
            if ($i + 1 < $date_count) {
                $next = date("d", $end);
                array_push($b, $next);
            }
        }

        /*        $c = M('IntoApp')->where(['intime'=>['between',[$stamp1,$stamp2]]])->count(); //当月总活跃*/
        success(['a' => $a, 'b' => $b]);
    }
    /**
     *@
     */
    public function info(){
        $merchant = $this->merchant;
        $merchant_info = Db::name('merchants')->where(['member_id' => $merchant['member_id']])->find();

        //最高成交日峰值
        $code['merchants_id'] = $merchant['member_id'];
        $code['order_state'] = ['neq', 'wait_pay,cancel'];
        $summit = Db::name('order_merchants')->field('date,SUM(order_actual_price) as actual_price')
            ->where($code)->group('date')
            ->order("actual_price desc")->limit(1)
            ->find();
        if ($summit) {
            $where['merchants_id'] = $merchant['member_id'];
            $where['date'] = $summit['date'];
            $summit_total = Db::name('order_merchants')->where($where)->count();//总订单
            //$summit_total_price = Db::name('order_merchants')->where($where)->sum('order_actual_price');  //总金额

            $map['order_state'] = ['neq', 'wait_pay,cancel'];
            $summit_actual = Db::name('order_merchants')->where($where)->count();//实际订单
            $summit_actual_price = Db::name('order_merchants')->where($where)->sum('order_actual_price');  //实际金额
            $summit_ratio = $summit_actual / $summit_total;     //订单转化率
            if ($summit_ratio != 0) {
                $summit_ratio = sprintf('%.2f', $summit_ratio * 100) . '%';
            }
            //订单平均值

            if ($summit_actual != 0) {
                $summit_average = sprintf('%.2f', $summit_actual_price / $summit_actual);
            } else {
                $summit_average = 0;
            }
        }

        $this->assign(['summit_total' => $summit_total, 'summit_actual' => $summit_actual,
            'summit_actual_price' => $summit_actual_price, 'summit_ratio' => $summit_ratio, 'summit_average' => $summit_average]);

        //今日交易量
        $time = date("Y-m-d 00:00:00", time());
        $time ? $map['pay_time'] = ['gt', $time] : $map['pay_time'] = ['between time', [$time, date("Y-m-d H:i:s", strtotime($time) + 24 * 3600)]];
        $map['merchants_id'] = $merchant['member_id'];

        $today_total = Db::name('order_merchants')->where($map)->count();//总订单
        //$today_total_price = Db::name('order_merchants')->where($map)->sum('order_actual_price');  //总金额

        $map['order_state'] = ['neq', 'wait_pay,cancel'];
        $today_actual = Db::name('order_merchants')->where($map)->count();//实际订单
        $today_actual_price = Db::name('order_merchants')->where($map)->sum('order_actual_price');  //实际金额

        if ($today_actual != 0) {
            $today_ratio = sprintf('%.2f', $today_actual / $today_total * 100) . '%';
        } else {
            $today_ratio = 0;
        }
        //订单平均值

        if ($today_actual != 0) {
            $today_average = sprintf('%.2f', $today_actual_price / $today_actual);
        } else {
            $today_average = 0;
        }
        $this->assign(['today_total' => $today_total, 'today_actual' => $today_actual,
            'today_actual_price' => $today_actual_price, 'today_ratio' => $today_ratio, 'today_average' => $today_average]);
        $month = date("Y-m", time());
        $this->assign(['merchant_info' => $merchant_info, 'month' => $month]);
        return $this->fetch();
    }

    /**
     *商家基础信息
     */
    public function merchant(){

        if(Request::instance()->isAjax()) {
            $data = Request::instance()->post(false); // 获取所有的post变量（原始数组）
            $model = model('merchants');
            $sheng = input('sheng');
            $shi = input('shi');
            $qu = input('qu');
            $data['merchants_province'] = Db::name('Areas')->where(array('id' => $sheng))->value('name');
            $data['merchants_city'] = Db::name('Areas')->where(array('id' => $shi))->value('name');
            $data['merchants_country'] = Db::name('Areas')->where(array('id' => $qu))->value('name');
            $data['merchants_province'] ? $data['merchants_province'] : $data['merchants_province'] = '';
            $data['merchants_city'] ? $data['merchants_city'] : $data['merchants_city'] = '';
            $data['merchants_country'] ? $data['merchants_country'] : $data['merchants_country'] = '';
            $result = $model->check($data);
        }else {
            $merchant = $this->merchant;
            $re = Db::name('merchants')->where(['member_id' => $merchant['member_id']])->find();
            //省
            $sheng = Db::name('Areas')->where("level=1")->select();
            $this->assign('sheng', $sheng);
            if (!empty($re)) {
                $fid = Db::name('Areas')->where(array('name' => $re['merchants_province'], 'level' => 1))->value('id');
                if ($fid) {
                    $data['pid'] = $fid;
                    $data['level'] = 2;
                    $re['shi'] = Db::name('Areas')->where($data)->select();  //市
                } else {
                    $re['shi'] = null;
                }
                $fid2 = Db::name('Areas')->where(array('name' => $re['merchants_city'], 'level' => 2))->value('id');
                if ($fid2) {
                    $date['pid'] = $fid2;
                    $date['level'] = 3;
                    $re['qu'] = Db::name('Areas')->where($date)->select();  //区
                } else {
                    $re['qu'] = null;
                }
                $re['city_id'] = Db::name('Areas')->where(array('name' => $re['merchants_city'], 'level' => 2))->value('id');
                $re['area_id'] = Db::name('Areas')->where(array('name' => $re['merchants_country'], 'level' => 3))->value('id');
            }
            $parent_class = Db::name('goods_class')->where(['is_delete' => '0', 'parent_id' => '-1'])->select();
            $merchant_class = Db::name('goods_merchants_class')->where(['member_id'=>$merchant['member_id']])->value('class_id');
            $merchant_class = explode(',', $merchant_class);
            $this->assign(['re' => $re, 'parent_class' => $parent_class, 'merchant_class' => $merchant_class]);
            $url = $_SERVER['REQUEST_URI'];
            session('url', $url);
            return $this->fetch();
        }
    }

    /**
     * @获取市
     */
    public function get_area(){
        $value = input('value');
        $type = input('type');
        if (isset($value)){
            if ($type==1){
                $data['level'] = 2;
                $data['pid'] = array('eq',$value);
                $type_list="<option value=''>请选择（市）</option>";
                $shi = Db::name('Areas')->where($data)->select();
            }else {
                $data['level'] = 3;
                $data['pid'] = array('eq',$value);
                $type_list="<option value=''>请选择（区/县）</option>";
                $shi = Db::name('Areas')->where($data)->select();
            }
            foreach($shi as $k=>$v){
                $type_list.="<option value=".$shi[$k]['id'].">".$shi[$k]['name']."</option>";
            }
            echo $type_list;
        }
    }
    
    public function merchant_video(){
        $map = array();
        $merchant = $this->merchant;
        $name = input('name');
        $merchant_id = input('merchant_id');
        !empty($merchant_id)  ?   $map['member_id'] = $merchant_id : $map['member_id'] = $merchant['member_id'];
        !empty($name) && $map['title'] = array("like","%".$name."%");
        $map['is_del'] = 1;
        $num  = input('num');
        if (empty($num)){
            $num = 10;
        }
        $this->assign('nus',$num);
        $data= Db::name("video")->alias('a')
            ->field('title,video_id,video_img,url,watch_nums,zan,a.intime,is_shenhe')
            ->where($map)->order("a.intime desc")
            ->paginate($num,false,$config = ['query'=>array('name'=>$name)]);
        $page = $data->render();
        $count = Db::name("video")->where($map)->count(); // 查询满足要求的总记录数
        $this->assign(['list'=>$data,'count'=>$count,'page'=>$page]);
        $url =$_SERVER['REQUEST_URI'];
        session('url',$url);
        $this->display();
        return $this->fetch();
    }

    public function add_video(){
        if(Request::instance()->isAjax()){
            $data = Request::instance()->post();
            $data['member_id']  = $this->merchant['member_id'];
            $model = model('Video');
            $result = $model->edit($data);
        }else{
            return $this->fetch();
        }
    }

    public function edit_video(){
        $merchant  = $this->merchant;
        if(Request::instance()->isAjax()){
            $data = Request::instance()->post();
            $data['member_id'] = $merchant['member_id'];
            $model = model('Video');
            $result = $model->edit($data);
        }else{
            $data['member_id']  = $this->merchant['member_id'];
            $id = input('id');
            $re = Db::name('video')->where(['member_id'=>$merchant['member_id'],'video_id'=>$id])->find();
            $this->assign(['re'=>$re]);
            return $this->fetch('index/add_video');
        }
    }


    /**
     *@修改审核状态
     */
    public function change_video_shenhe(){
        if(Request::instance()->isAjax()){
            $id = input('id');
            $status = Db::name('Video')->where(['video_id'=>$id])->value('is_shenhe');
            $abs = 3 - $status;
            //$arr = ['默认状态','开启状态'];
            $result = Db::name('Video')->where(['video_id'=>$id])->update(['is_shenhe'=>$abs]);
            if($result){
                echo json_encode(array('status'=>'ok','info'=>$abs));
                exit;
            }else{
                echo json_encode(array('status'=>'error','info'=>'切换状态失败'));
                exit;
            }
        }
    }

    /**
     *@删除视频
     */
    public function del_video(){
        if(Request::instance()->isAjax()) {
            $id = input('ids');
            $data['video_id'] = array('in', $id);
            $user = Db::name('Video')->where($data)->update(['is_del' => 2]);
            if ($user) {
                echo json_encode(['status' => "ok", 'info' => '删除记录成功!', 'url' => session('url')]);
            } else {
                echo json_encode(['status' => "error", 'info' => '删除记录失败!']);
            }
        }
    }

    public function _empty(){
        //根据当前控制器名来判断要执行那个城市的操作
        $this->view->engine->layout(false);
        return $this->fetch('common/error');

    }

    function getArrSet($arrs, $_current_index = -1)
    {
        //总数组
        static $_total_arr;
        //总数组下标计数
        static $_total_arr_index;
        //输入的数组长度
        static $_total_count;
        //临时拼凑数组
        static $_temp_arr;

        //进入输入数组的第一层，清空静态数组，并初始化输入数组长度
        if ($_current_index < 0) {
            $_total_arr = array();
            $_total_arr_index = 0;
            $_temp_arr = array();
            $_total_count = count($arrs) - 1;
            $this->getArrSet($arrs, 0);
        } else {
            //循环第$_current_index层数组
            foreach ($arrs[$_current_index] as $v) {
                //如果当前的循环的数组少于输入数组长度
                if ($_current_index < $_total_count) {
                    //将当前数组循环出的值放入临时数组
                    $_temp_arr[$_current_index] = $v;
                    //继续循环下一个数组
                    $this->getArrSet($arrs, $_current_index + 1);

                } //如果当前的循环的数组等于输入数组长度(这个数组就是最后的数组)
                else if ($_current_index == $_total_count) {
                    //将当前数组循环出的值放入临时数组
                    $_temp_arr[$_current_index] = $v;
                    //将临时数组加入总数组
                    $_total_arr[$_total_arr_index] = $_temp_arr;
                    //总数组下标计数+1
                    $_total_arr_index++;
                }

            }
        }

        return $_total_arr;
    }
    public function test(){
        $arr = [
            ['a','b','c'],
            ['A','B','C'],
            ['1','2','3']
        ];
        pre($this->getArrSet($arr));
    }

    public function test1(){
        $list = Db::name('goods')->column('goods_id,goods_name');
        if(in_array('衣服',$list)){
            pre(1);
        }
    }

    public function test2(){
        $a = [
            ['a','b'],
            ['b','c']
        ];
        $b = ['b','a'];
        pre(in_array($b,$a));
        $c = ['a','b'];
        $d = ['b','a'];
        pre($c == $d);

    }





}
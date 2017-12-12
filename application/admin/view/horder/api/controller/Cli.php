<?php
namespace app\api\controller;
use lib\Easemob;
use Qiniu\QiniuPili;
use think\Controller;
use think\View;
use think\Db;
use \think\Request;

class Cli extends Common
{
    /**
     *@列出七牛正在直播的流，不在里面则改变直播状态。
     */
    public function check_online(){
        //获取七牛哪里的活跃流(array)
        $qn = new QiniuPili();
        $streamKey_list = $qn->listLiveStreams();
        //获取直播列表正在直播的视频数量
        $count =$live = DB::name('Live')->where(['live_status' => '1'])->count();
        //进行分页循环处理
        $num = ceil($count/50);
        for ($i = 0; $i < $num; $i++) {
            $live = DB::name('Live')->field("live_id, user_id, play_img,title,room_id,lebel,intime,stream_key")->where(['live_status' => '1'])->limit($i*50,50)->select();
            if (empty($live)) break;
            foreach ($live as $k => $v) {
                //循环判断直播表里面正在直播的流是否在七牛上
                if(!in_array($v['stream_key'],$streamKey_list)){
                    //如果不在修改为下线状态并且进行视频保存
                    DB::name('Live')->where(['live_id'=>$v['live_id']])->update(['live_status'=>2,'is_normal_exit'=>2,'end_time'=>time(),'uptime'=>time()]);
                    //保存保存直播视频
                    $fname = $qn->save_vido($v['stream_key']);
                    if(!empty($fname["error"]));continue;
                    if ($fname['fname']) {
                        $data = [
                            'live_id' => $v['live_id'],
                            'user_id' => $v['user_id'],
                            'play_img' => $v['play_img'],
                            'title' => $v['title'],
                            'url' => config("speed_domain") . $fname['fname'],
                            'intime' => time(),
                            'room_id' => $v['room_id'],
                            'date'=>date('Y-m-d',time()),
                            'lebel'=>$v['lebel'],
                            'livewindow_type' => 1,
                            'stream_key' => $v['stream_key'],
                            'live_time' => $v["intime"],
                        ];
                        //如果回放中存在的进行结束时间的修改
                        $live_id = DB::name("live_store")->where(["stream_key" =>$data["stream_key"]])->value('live_id');
                        if(!$live_id){
                            DB::name("merchants")->where(["member_id"=>$v["user_id"]])->update(["live_id"=>0]);
                            DB::name('Live_store')->insert($data);
                        }else{
                            DB::name("merchants")->where(["member_id"=>$v["user_id"]])->update(["live_id"=>0]);
                            DB::name("Live_store")->update(["end_time"=>time(),'uptime'=>time()]);
                        }
                    }
                }else{
                    //如果在修改为直播状态并且进行视频保存
                    DB::name('Live')->where(['live_id'=>$v['live_id']])->update(['live_status'=>1,'is_normal_exit'=>1,'uptime'=>time(),"end_time" => 0]);
                    DB::name("merchants")->where(["member_id"=>$v["user_id"]])->update(["live_id"=>$v['live_id']]);
                }
            }
            set_time_limit(0);
        }
    }

    /***
     * 每过2分钟一个僵尸粉,如果直播间人数超过10人,则不加僵尸粉
     */
    public function add_fans(){
        //获取直播间可以设置的僵尸粉的数量
        $most_num = DB::name('System')->where(['id'=>1])->value('live_most_num');
        $one_minutes_num = DB::name("system")->where(["id"=>1])->value("one_minutes_num");
        $hx = new Easemob();
        for ($i = 0; $i < 50; $i++) {
            set_time_limit(0);
            //获取正在直播的值的直播间
            $live = DB::name('Live')->where(['live_status' => '1'])->page($i)->limit(50)->select();
            if (!$live) break;
            foreach($live as $k=>$v){
                $count = DB::name('Live_number')->where(['live_id'=>$v['live_id']])->count();
                if($count<$most_num){
                    $live_number = DB::name('Live_number')->where(['live_id'=>$v['live_id']])->select();
                    if ($live_number){
                        //获取直播间现在的人数
                        $user_ids = array_map(function($v){ return $v['user_id2'];},$live_number);
                        //设置每个频率时间段添加的僵尸粉
                        $fans = DB::name('member')->where(['is_fans'=>2,'member_id'=>['not in',$user_ids]])->order('rand()')->limit($one_minutes_num)->select();
                        if ($fans){
                            foreach ($fans as $a=>$b){
                                DB::name('Live_number')->insert(['live_id'=>$v['live_id'],'user_id'=>$v['user_id'],'user_id2'=>$b['member_id'],'intime'=>time()]);
                                DB::name('Live')->where(['live_id'=>$v['live_id']])->setInc('nums');
                                DB::name('Live')->where(['live_id'=>$v['live_id']])->setInc('watch_nums');
                            }
                            $hx->adduserChatRoom($fans[0]['hx_username'],$v['room_id']); //其中一个加入聊天室
                            //$get_gradeinfo = get_gradeinfo($fans[0]['grade']);
                            $ext = [
                                'user_id'=>$fans[0]['member_id'],
                                'username'=>$fans[0]['username'],
                                'userimg'=>$fans[0]['header_img'],
                                'intoroom'=>"1",
//                                'usergrade'=>$fans[0]['grade'],
//                                'authName'=>""
                            ];
                            $re = $hx->sendText($fans[0]['hx_username'],$v['room_id'],"进入了直播间",$ext);   //给聊天室发消息
                        }
                    }else{
                        $fans = DB::name('member')->where(['is_fans'=>2])->order('rand()')->limit($one_minutes_num)->select();
                        if ($fans){
                            foreach ($fans as $a=>$b){
                                DB::name('Live_number')->insert(['live_id'=>$v['live_id'],'user_id'=>$v['user_id'],'user_id2'=>$b['member_id'],'intime'=>time()]);
                                DB::name('Live')->where(['live_id'=>$v['live_id']])->setInc('nums');
                                DB::name('Live')->where(['live_id'=>$v['live_id']])->setInc('watch_nums');
                            }
                            $hx->adduserChatRoom($fans[0]['hx_username'],$v['room_id']); //其中一个加入聊天室
                            //$get_gradeinfo = get_gradeinfo($fans[0]['grade']);
                            $ext = [
                                'user_id'=>$fans[0]['member_id'],
                                'username'=>$fans[0]['username'],
                                'userimg'=>$fans[0]['header_img'],
                                'intoroom'=>"1",
//                                'usergrade'=>$fans[0]['grade'],
//                                'authName'=>""
                            ];
                            $hx->sendText($fans[0]['hx_username'],$v['room_id'],"进入了直播间",$ext);   //给聊天室发消息
                        }
                    }
                }
            }
        }
    }
    /**
     * 批量注册僵尸粉
     */
    public function reg_fans(){
        $count = DB::name("member")->where(["is_fans"=>2])->count();
        $page = ceil($count/50);
        $hx = new Easemob();
        for ($i=0;$i<$page;$i++){
            $fans_list = DB::name("member")->field("hx_username,member_id,hx_password,hx_password")->where(["is_fans"=>2])->limit($i*50,50)->select();
            foreach ($fans_list as $k=>$v) {
                $re = $hx->huanxin_zhuce($v["hx_username"], '123456');
                if(!$re){
                    continue;
                }
            }
        }
    }
}
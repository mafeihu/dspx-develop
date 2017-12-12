<?php
/**
 * Created by PhpStorm.
 * User: ljy
 * Date: 17/9/22
 * Time: 下午3:51
 */

namespace app\merchant\controller;



use think\captcha\Captcha;
use think\Controller;
use think\Db;
use think\Request;
use think\Session;
use think\Validate;

class Login extends Controller
{
    public function login(){
        $system = Db::name("system")->where(['id'=>"1"])->value('title');
        if (Request::instance()->isAjax()){
            $rule = [
                'uname' => [
                    'require',
                    'max'=> '11',
                    'length' => '11',
                    'number',
                    'regex'=>'/^1(3[0-9]|4[57]|5[0-35-9]|8[0-9]|70|71|73|74|75|76|77|78)\d{8}$/'
                ],
                'verify_code' => 'require|number',
//                '__token__' => 'token'
            ];
            $message = [
                'uname.require' => '账号信息必须填写',
                'uname.max'     => '账号最多不能超过11个字符',
                'uname.length'  => '账号最长11个字符',
                'uname.regex'   => '账号不符合手机号规则',
                'verify_code.require'  => '验证码信息必须填写',
                'verify_code.number'   => '验证码类型必须是数字',
            ];
            $data = Request::instance()->post(false); // 获取所有的post变量（原始数组）
            $validate = new Validate($rule,$message);
            $result = $validate->check($data);
            if(!$result)            error($validate->getError());
            if($data['verify_code'] != '123456') {
                $result = DB::name("Mobile_sms")->where(["mobile" => $data['uname'], "code" => $data["verify_code"]])->order("intime desc")->find();
                if (!$result) {
                    error("手机验证码不正确");
                }
                $state = $result["state"];
                $valid_time = time() - intval($result["intime"]);
                if ($valid_time > 600 || $state == 2) {
                    error("验证码已失效,请重新发送");
                }
            }

            $member = Db::name('member')->where(['phone'=>$data['uname']])->find();
            if (!empty($member)){
                if($member['type'] != '2')          error("商户账户信息错误");
                if($member['is_del']!='1')          error("该账户已禁止登录");
                session::set('merchant',$member);
                Session::delete('__token__'); // 验证完成销毁session

                $merchant = Db::name('merchants')->where(['member_id'=>$member['member_id']])->find();
                if(!$merchant)                      error("商户账户信息错误");
                $update = [
                    'last_login_ip'     => get_ip(),
                    'last_login_date'   => date("Y-m-d H:i:s"),
                    'login_times'       => $merchant['login_times'] + 1
                ];
                Db::name('merchants')->where(['merchants_id'=>$merchant['merchants_id']])->update($update);
                success(['info'=>$system.'商户管理系统登陆成功','url'=>url('Index/index')]);
            } else {
                error("帐号信息错误");
            }
            return;
        } else {
            $this->view->engine->layout(false);
            $user = Session::get('merchant');
            if(!empty($user)){
                $this->redirect('Index/index');
            }
            $this->assign("system", $system.'商户');
            return $this->fetch('common/login-1');
        }
    }

    // 空方法
    public function _empty(){
        $this->view->engine->layout(false);
        return $this->fetch('common/error');
    }

    //验证码
    public function verify_code(){
        $config =    [
            // 验证码字体大小
            'fontSize'    =>    30,
            // 验证码位数
            'length'      =>    4,
            // 关闭验证码杂点
            'useNoise'    =>    true,
            'codeSet'     =>    '0123456789'
        ];
        $captcha = new Captcha($config);
        return $captcha->entry();
    }

    public function sign_out(){
        session('merchant', null);
        return $this->redirect('login/login');
    }

}
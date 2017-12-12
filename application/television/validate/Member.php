<?php
/**
 * Created by PhpStorm.
 * User: ljy
 * Date: 17/9/29
 * Time: 下午2:32
 */

namespace app\television\validate;


use think\Validate;

class Member extends Validate
{
    protected $rule = [
        'phone'  => [
            'require',
            'length'=>11,
            'number',
            'regex' => '/^1(3[0-9]|4[57]|5[0-35-9]|8[0-9]|70|71|73|74|75|76|77|78)\d{8}$/'
        ],
        'password'     =>  'require',
        'tv_dashang_scale'=>'require|number|between:0,100',
        'sell_scale'=>'require|number|between:0,100',
        'tag'=>"require",
        'verify_code'     => 'require|number',
        '__token__' => 'token',
    ];
    protected  $message = [
        'phone.require'              => '用户账户不能为空',
        'phone.length'               => '用户账号字符长度错误',
        'phone.number'               => '用户账号字符必须是数字',
        'phone.unique'              => '此手机号已存在',
        'phone.regex'                => '用户账号不满足手机号规则',
        'password.require'          => '用户登录密码必须填写',
        'tv_dashang_scale.require'    => '请设置主播获取打赏比例',
        'tv_dashang_scale.number'      => '打赏比例只能为整数',
        'tv_dashang_scale.between'      => '打赏比例值为0~100',
        'sell_scale.require'        => '请设置主播销售分润比例',
        'sell_scale.number'         => '分润比例只能为整数',
        'sell_scale.between'         => '分润比例值为0~100',
        'tag.require'                =>'请选择直播分类标签',
        'verify_code.require'       => '验证码信息必须填写',
        'verify_code.number'        => '验证码类型必须是数字',
    ];
    //添加验证场景
    protected $scene = [
        'login'   =>  [
            'phone' => "require",
            'password'=>"require",
        ],
        'add'     =>  [
            'phone'=>  [
                'require',
                'unique'=>'member,phone',
                'length'=>11,
                'number',
                'regex' => '/^1(3[0-9]|4[57]|5[0-35-9]|8[0-9]|70|71|73|74|75|76|77|78)\d{8}$/'
            ],
            'dashang_scale'=>'require|number|between:0,100',
            //'sell_scale'=>'require|number|between:0,100',
            'tag'=>"require",
        ],
        'edit' =>  [
            'phone'=>  [
                'require',
                'unique'=>'member,phone^member_id',
                'length'=>11,
                'number',
                'regex' => '/^1(3[0-9]|4[57]|5[0-35-9]|8[0-9]|70|71|73|74|75|76|77|78)\d{8}$/'
            ],
            'dashang_scale'=>'require|number|between:0,100',
            //'sell_scale'=>'require|number|between:0,100',
            'tag'=>"require",
        ]
    ];





}
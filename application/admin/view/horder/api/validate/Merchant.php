<?php
/**
 * Created by PhpStorm.
 * User: ljy
 * Date: 17/10/11
 * Time: 上午9:32
 */
namespace app\api\validate;
use think\Validate;
class Merchant extends Validate{
    protected $rule =   [
        'contact_name'              => 'require|regex:/^[\x{4e00}-\x{9fa5}]{2,10}$/u',//商户姓名
        'contact_mobile'             => [
            'require',
            'unique'=>'merchants,contact_mobile',
            'length'=>11,
            'number',
            'regex' => '/^1(3[0-9]|4[57]|5[0-35-9]|8[0-9]|70|71|73|74|75|76|77|78)\d{8}$/'
        ],//联系方式
        'merchants_name'           =>[
            'require',
            'unique'=>'merchants,merchants_name'
                                        ],
        'merchants_address'       =>'require',//店铺地址
    ];
    protected $message  =   [
        'contact_name.require'                      => '请输入身份证上的姓名',
        'contact_name.regex'                        => '输入的姓名只能是中文',

        'contact_mobile.unique'                             => '手机号已被使用',
        'contact_mobile.length'                             => '手机号码为11位数字',
        'contact_mobile.num'                                => '手机号码为11位数字',
        'contact_mobile.regex'                              => '请输入正确的手机号码',

        'merchants_name.require'                   =>'请输入店铺名称',
        'merchants_name.unique'                    =>"店铺名称已被使用",

        'merchants_address.require'               =>"请输入店铺详细地址",
    ];
}
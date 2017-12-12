<?php
/**
 * Created by PhpStorm.
 * User: ljy
 * Date: 17/9/22
 * Time: 下午5:20
 */

return [
    // +----------------------------------------------------------------------
    // | 应用设置
    // +----------------------------------------------------------------------
    // 定义空控制器
    'empty_controller'      => 'MyError',
    //模版设置
    'template'               => [
        // 模板引擎类型 支持 php think 支持扩展
        'type'         => 'Think',
        // 模板路径
        'view_path'    => '',
        // 模板后缀
        'view_suffix'  => 'html',
        // 模板文件名分隔符
        'view_depr'    => DS,
        // 模板引擎普通标签开始标记
        'tpl_begin'    => '{',
        // 模板引擎普通标签结束标记
        'tpl_end'      => '}',
        // 标签库标签开始标记
        'taglib_begin' => '{',
        // 标签库标签结束标记
        'taglib_end'   => '}',
        'layout_on'    => true,
        'layout_name'  => 'layout'
    ],

    //分页设置
    'paginate'               => [
        'type'      => 'lib\Page',
        'var_page' => 'p',
        'list_rows' => 15,
    ],
    'session'                => [
        'id'             => '',
        // SESSION_ID的提交变量,解决flash上传跨域
        'var_session_id' => '',
        // SESSION 前缀
        'prefix'         => 'dspx_merchant',
        // 驱动方式 支持redis memcache memcached
        'type'           => '',
        // 是否自动开启 SESSION
        'auto_start'     => true,
        'expire'         => '36000'
    ],

    //导航菜单
    'nav' => [
        [
            'menu' => [
                [
                    'title' => '信息概览',
                    'name' => 'Index/index',
                    'icon' => ''
                ],
                [
                    'title' => '订单概览',
                    'name' => 'Index/info',
                    'icon' => ''
                ],
                [
                'title' => '店铺信息',
                'name' => 'Index/merchant',
                'icon' => ''
                ],
                [
                    'title' => '导购视频',
                    'name' => 'Index/merchant_video',
                    'icon' => ''
                ]
            ],
            'title'=>'店铺信息',
            'icon'=>'',
        ],
        [
            'menu' => [
                [
                    'title' => '品牌列表',
                    'name' => 'Goods/index',
                    'icon' => ''
                ],
                [
                    'title' => '商品列表',
                    'name' => 'Goods/goods_list',
                    'icon' => ''
                ],
                [
                    'title' => '已删除商品',
                    'name' => 'Goods/is_del_goods',
                    'icon' => ''
                ],
//                [
//                    'title' => '优惠券',
//                    'name' => 'Event/coupon',
//                    'icon' => ''
//                ]
            ],
            'title'=>'商品信息',
            'icon'=>'&#xe620;',
        ],
        [
            'menu' => [
                [
                    'title' => '今日新增',
                    'name' => 'horder/index',
                    'icon' => ''
                ],
                [
                    'title' => '待支付',
                    'name' => 'horder/to_be_pay',
                    'icon' => ''
                ],
                [
                'title' => '待发货',
                'name' => 'horder/to_be_drawer',
                'icon' => ''
                ],
                [
                    'title' => '待收货',
                    'name' => 'horder/to_be_accept',
                    'icon' => ''
                ],
                [
                    'title' => '待评价',
                    'name' => 'horder/to_be_check',
                    'icon' => ''
                ],
                [
                    'title' => '已完成',
                    'name' => 'horder/complete',
                    'icon' => ''
                ],
                [
                    'title' => '已退款',
                    'name' => 'horder/to_be_returns',
                    'icon' => ''
                ],
                [
                    'title' => '已取消',
                    'name' => 'horder/cancel_order',
                    'icon' => ''
                ],
                [
                    'title' => '已删除',
                    'name' => 'horder/is_del_order',
                    'icon' => ''
                ],
                [
                    'title' => '售后订单',
                    'name' => 'horder/refund',
                    'icon' => ''
                ],
                [
                    'title' => '全部订单',
                    'name' => 'horder/to_all_order',
                    'icon' => ''
                ]
            ],
            'title'=>'订单信息',
            'icon'=>'&#xe627;',
        ]
    ]
];
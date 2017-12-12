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
        'prefix'         => 'dspx_television',
        // 驱动方式 支持redis memcache memcached
        'type'           => '',
        // 是否自动开启 SESSION
        'auto_start'     => true,
        'expire'         => '7200'
    ],

    //导航菜单
    'nav' => [
        [
            'menu' => [
                [
                    'title' => '商户列表',
                    'name' => 'Merchants/index',
                    'icon' => ''
                ],
                [
                    'title' => '删除商户列表',
                    'name' => 'Merchants/del_merchants_list',
                    'icon' => ''
                ],
            ],
            'title'=>'商户管理',
            'icon'=>'',
        ],
        [
            'menu' => [
                [
                    'title' => '主播列表',
                    'name' => 'Anchor/index',
                    'icon' => ''
                ],
                [
                    'title' => '删除主播列表',
                    'name' => 'Anchor/del_anchor_list',
                    'icon' => ''
                ],
            ],
            'title'=>'主播管理',
            'icon'=>'',
        ],
        [
            'menu' => [
                [
                    'title' => '城市电视台',
                    'name' => 'Television/city',
                    'icon' => ''
                ],
                [
                    'title' => '区县电视台',
                    'name' => 'Television/country',
                    'icon' => ''
                ],
            ],
            'title'=>'电视台管理',
            'icon'=>'',
        ],
        [
            'menu' => [
                [
                    'title' => '今日收益',
                    'name' => 'earnings/today_sell',
                    'icon' => ''
                ],
                [
                    'title' => '昨日收益',
                    'name' => 'earnings/yesterday_sell',
                    'icon' => ''
                ],
                [
                    'title' => '全部收益',
                    'name' => 'earnings/all_sell',
                    'icon' => ''
                ],
            ],
            'title'=>'销售结算统计',
            'icon'=>'&#xe627;',
        ],
        [
            'menu' => [
                [
                    'title' => '今日收益',
                    'name' => 'earnings/today_live',
                    'icon' => ''
                ],
                [
                    'title' => '昨日收益',
                    'name' => 'earnings/yesterday_live',
                    'icon' => ''
                ],
                [
                    'title' => '全部收益',
                    'name' => 'earnings/all_live',
                    'icon' => ''
                ],
            ],
            'title'=>'直播统计管理',
            'icon'=>'&#xe627;',
        ],
        [
            'menu' => [
                [
                    'title' => '直播收益列表',
                    'name' => 'Caiwu/anchor',
                    'icon' => ''
                ],
                [
                    'title' => '商户销售报表',
                    'name' => 'Caiwu/merchants',
                    'icon' => ''
                ]
            ],
            'title'=>'财务管理',
            'icon'=>'',
        ]
    ]
];
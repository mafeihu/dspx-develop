<?php
/**
 * Created by PhpStorm.
 * User: ljy
 * Date: 17/10/20
 * Time: 下午5:34
 */

namespace app\merchant\controller;

use think\Db;
use think\Request;
use think\Session;
class Goods extends Base
{
    /**
     *品牌列表
     */
    public function index(){
        $merchant = $this->merchant;
        $map = [];
        $name = input('name'); //
        $brand_state = input('brand_state'); //
        !empty($name) &&  $map['brand_name'] = ['like', '%' . $name . '%'];
        !empty($brand_state) && $map['brand_state'] = $brand_state;
        $map['merchant_id'] = $merchant['member_id'];
        $map['is_delete'] = '0';
        $num = input('num');
        if (empty($num)) {
            $num = 10;
        }
        $this->assign('nus', $num);
        $count = Db::name('goods_brand')->where($map)->count();
        $data = Db::name("goods_brand")->where($map)->order("brand_state asc,sort desc")
              ->paginate($num,false,$config = ['query'=>array('name'=>$name,'brand_state'=>$brand_state)]);
        $page = $data->render();
        $this->assign(['list' => $data, 'page' => $page,'count'=>$count]);
        $url = $_SERVER['REQUEST_URI'];
        session('url', $url);
        return $this->fetch();
    }

    /**
     *添加商品
     */
    public function insert_brand(){
        if(Request::instance()->isAjax()){
            $data = Request::instance()->post(false); // 获取所有的post变量（原始数组）
            $merchant = $this->merchant;
            $data['merchant_id']    =   $merchant['member_id'];
            if(empty($data['brand_id'])){
                $data['brand_uuid'] = get_guid();
            }
            $model = model('GoodsBrand');
            $result = $model->edit($data);
        }else{
            return $this->fetch();
        }
    }

    /**
     *添加品牌
     */
    public function edit_brand(){
        $model = model('GoodsBrand');
        if(Request::instance()->isAjax()){
            $data = Request::instance()->post(false); // 获取所有的post变量（原始数组）
            $merchant = $this->merchant;
            $data['merchant_id']    =   $merchant['member_id'];
            if(empty($data['brand_id'])){
                $data['brand_uuid'] = get_guid();
            }
            $result = $model->edit($data,'edit');
        }else{
            $brand_uuid = input('uuid');
            $where['brand_uuid'] = $brand_uuid;
            $re = $model->queryByCode($where);
            $this->assign(['re'=>$re]);
            return $this->fetch('goods/insert_brand');
        }
    }

    /**
     *删除品牌
     */
    public function del_brand(){
        if(Request::instance()->isAjax()) {
            $ids = input('ids');
            if(empty($ids))     error("删除失败");
            $merchant = $this->merchant;
            $map['merchant_id']    =   $merchant['member_id'];
            $map['brand_id']    =   ['in',$ids];
            $model = model('GoodsBrand');
            $result = $model->soft_del($map);
        }
    }

    /**
     *@复制
     */
    public function copy_brand(){
        if(Request::instance()->isAjax()){
            $id = input('id');
            $model = model('GoodsBrand');
            $map['brand_id'] = $id;
            $map = $model->queryByCode($map);
            if($map){
                $data['brand_name'] = $map['brand_name'];
                $data['brand_desc'] = $map['brand_desc'];
                $data['brand_img']  = $map['brand_img'];
                $data['merchant_id']= $map['merchant_id'];
                $data['brand_state']= 2;
                $data['sort']= $map['sort'];
                $data['brand_uuid'] = get_guid();
                $result = $model->edit($data);
            }else{
                return error("要复制的品牌没有找到");
            }
        }
    }

    /**
     *@品牌状态
     */
    public function change_brand_state(){
        if(Request::instance()->isAjax()){
            $id = input('id');
            $status = Db::name('goods_brand')->where(['brand_id'=>$id])->value('brand_state');
            $abs = 3 - $status;
            $arr = ['1','2'];
            $result = Db::name('goods_brand')->where(['brand_id'=>$id])->update(['brand_state'=>$abs]);
            if($result){
                echo json_encode(array('status'=>'ok','info'=>$arr[2-$status]));
                exit;
            }else{
                echo json_encode(array('status'=>'error','info'=>'切换状态失败'));
                exit;
            }
        }

    }

    /**
     *@品牌上移排序
     */
    public function plus_brand_sort(){
        if(Request::instance()->isAjax()){
            $merchant = $this->merchant;
            $brand_id = input('brand_id');
            $name = input('name'); //
            $brand_state = input('brand_state'); //
            !empty($name) &&  $map['brand_name'] = ['like', '%' . $name . '%'];
            !empty($brand_state) && $map['brand_state'] = $brand_state;
            $check = Db::name('goods_brand')->where(['brand_id'=>$brand_id])->find();
            $map['sort'] = ['gt',$check['sort']];
            $map['brand_state'] = 1;
            $map['is_delete'] = '0';
            $map['merchant_id'] = $merchant['member_id'];
            if($check['brand_state'] != '1')        error("请先上架再操作");

            $last = Db::name('goods_brand')->where($map)
                ->order("sort asc,create_time desc")->limit(1)->select();
            if(empty($last)){
                error('不能移动');
            }else{
                $sort = $last[0]['sort'];
                $result = Db::name('goods_brand')->where(['brand_id'=>$brand_id])->update(['sort'=>$sort]);
                Db::name('goods_brand')->where(['brand_id'=>$last[0]['brand_id']])->update(['sort'=>$check['sort']]);
            }
            if($result){
                success('操作成功');
            }else{
                error('操作失败');
            }
        }
    }

    /**
     *@下移排序
     */
    public function minus_brand_sort(){
        if(Request::instance()->isAjax()){
            $merchant = $this->merchant;
            $brand_id = input('brand_id');
            $name = input('name'); //
            $brand_state = input('brand_state'); //
            !empty($name) &&  $map['brand_name'] = ['like', '%' . $name . '%'];
            !empty($brand_state) && $map['brand_state'] = $brand_state;
            $check = Db::name('goods_brand')->where(['brand_id'=>$brand_id])->find();
            $map['sort'] = ['lt',$check['sort']];
            $map['brand_state'] = 1;
            $map['is_delete'] = '0';
            $map['merchant_id'] = $merchant['member_id'];
            if($check['brand_state'] != '1')        error("请先上架再操作");

            $last = Db::name('goods_brand')->where($map)
                ->order("sort desc,create_time desc")->limit(1)->select();
            if(empty($last)){
                error('不能移动');
            }else{
                $sort = $last[0]['sort'];
                $result = Db::name('goods_brand')->where(['brand_id'=>$brand_id])->update(['sort'=>$sort]);
                Db::name('goods_brand')->where(['brand_id'=>$last[0]['brand_id']])->update(['sort'=>$check['sort']]);
            }
            if($result){
                success('操作成功');
            }else{
                error('操作失败');
            }
        }
    }

    /**
     *商品列表
     */
    public function goods_list(){
        $merchant = $this->merchant;
        $map = [];
        $name = input('name'); // 获取所有的post变量（原始数组）
        $goods_state = input('goods_state'); // 获取所有的post变量（原始数组）
        $parent_class = input('parent_class'); // 获取所有的post变量（原始数组）
        $seed_class = input('seed_class'); // 获取所有的post变量（原始数组）
        !empty($name) &&  $map['goods_name'] = ['like', '%' . $name . '%'];
        !empty($goods_state) && $map['goods_state'] = $goods_state;
        !empty($parent_class) && $map['parent_class'] = $parent_class;
        !empty($seed_class) && $map['seed_class'] = $seed_class;
        $map['is_delete'] = '0';
        $map['merchants_id']    =   $merchant['member_id'];
        $num = input('num');
        if (empty($num)) {
            $num = 10;
        }
        $this->assign('nus', $num);
        $count = Db::name('goods')->where($map)->count();
        $data = Db::name("goods")
            ->where($map)->order("goods_state asc,sort desc,create_time desc")
            ->paginate($num,false,$config = ['query'=>array('name'=>$name,'goods_state'=>$goods_state,'parent_class'=>$parent_class,'seed_class'=>$seed_class)]);
        $page = $data->render();
        $parent_class = Db::name('goods_class')->alias('a')
            ->field('a.class_id,a.class_name')
            ->join('th_goods_merchants_class b','FIND_IN_SET(a.class_id,b.class_id)')
            ->where(['a.class_state'=>'1','a.is_delete'=>'0','b.member_id'=>$merchant['member_id']])
            ->select();
        $this->assign(['list' => $data, 'page' => $page,'count'=>$count, 'parent_class'=>$parent_class]);
        $url = $_SERVER['REQUEST_URI'];
        session('url', $url);
        return $this->fetch();
    }

    /**
     *商品列表
     */
    public function is_del_goods(){
        $merchant = $this->merchant;
        $map = [];
        $name = input('name'); // 获取所有的post变量（原始数组）
        $goods_state = input('goods_state'); // 获取所有的post变量（原始数组）
        $parent_class = input('parent_class'); // 获取所有的post变量（原始数组）
        $seed_class = input('seed_class'); // 获取所有的post变量（原始数组）
        !empty($name) &&  $map['goods_name'] = ['like', '%' . $name . '%'];
        !empty($goods_state) && $map['goods_state'] = $goods_state;
        !empty($parent_class) && $map['parent_class'] = $parent_class;
        !empty($seed_class) && $map['seed_class'] = $seed_class;
        $map['is_delete'] = '1';
        $map['merchants_id']    =   $merchant['member_id'];
        $num = input('num');
        if (empty($num)) {
            $num = 10;
        }
        $this->assign('nus', $num);
        $count = Db::name('goods')->where($map)->count();
        $data = Db::name("goods")
            ->where($map)->order("goods_state asc,sort desc,create_time desc")
            ->paginate($num,false,$config = ['query'=>array('name'=>$name,'goods_state'=>$goods_state,'parent_class'=>$parent_class,'seed_class'=>$seed_class)]);
        $page = $data->render();
        $parent_class = Db::name('goods_class')->alias('a')
            ->field('a.class_id,a.class_name')
            ->join('th_goods_merchants_class b','FIND_IN_SET(a.class_id,b.class_id)')
            ->where(['a.class_state'=>'1','a.is_delete'=>'0','b.member_id'=>$merchant['member_id']])
            ->select();
        $this->assign(['list' => $data, 'page' => $page,'count'=>$count, 'parent_class'=>$parent_class]);
        $url = $_SERVER['REQUEST_URI'];
        session('url', $url);
        return $this->fetch();
    }

    /**
     *添加商品
     */
    public function insert_goods(){
        $merchant = $this->merchant;
        if(Request::instance()->isAjax()) {
            $data = Request::instance()->post();
            $data['merchants_id'] = $merchant['member_id'];
            $data['goods_class'] = join(',',$data['goods_class']);
            if(empty($data['goods_uuid'])){
                $data['goods_uuid'] = get_guid();
            }
            $model = model('Goods');
            $result = $model->edit($data);
        }else{
            $parent_class = Db::name('goods_class')->alias('a')
                ->field('a.class_id,a.class_name')
                ->join('th_goods_merchants_class b','FIND_IN_SET(a.class_id,b.class_id)')
                ->where(['a.class_state'=>'1','a.is_delete'=>'0','b.member_id'=>$merchant['member_id']])
                ->select();
            $brand = Db::name('goods_brand')->where(['merchant_id'=>$merchant['member_id'],'is_delete'=>'0','brand_state'=>'1'])->select();
            $specification = Db::name('goods_specification')->where(['parent_id'=>'-1'])->select();
            $this->assign(['parent_class'=>$parent_class,'brand'=>$brand,'specification'=>$specification]);
            return $this->fetch();
        }
    }

    /**
     *编辑商品
     */
    public function edit_goods(){
        $merchant = $this->merchant;
        if(Request::instance()->isAjax()) {
            $data = Request::instance()->post();
            $data['merchants_id'] = $merchant['member_id'];
            $data['goods_class'] = join(',',$data['goods_class']);
            $data['imgs'] = join(',',$data['imgs']);
            if(empty($data['goods_uuid'])){
                $data['goods_uuid'] = get_guid();
            }
            $model = model('Goods');
            $result = $model->edit($data,'edit');
        }else{
            $parent_class = Db::name('goods_class')->alias('a')
                ->field('a.class_id,a.class_name')
                ->join('th_goods_merchants_class b','FIND_IN_SET(a.class_id,b.class_id)')
                ->where(['a.class_state'=>'1','a.is_delete'=>'0','b.member_id'=>$merchant['member_id']])
                ->select();
            $brand = Db::name('goods_brand')->where(['merchant_id'=>$merchant['member_id'],'is_delete'=>'0','brand_state'=>'1'])->select();
            $goods_uuid = input('goods_uuid');
            $model = model('Goods');
            $where['goods_uuid'] = $goods_uuid;
            $goods = $model->queryGoods($where);
            $goods['imgs'] = explode(',',$goods['imgs']);
            if(!empty($goods['goods_tag']))  $goods['goods_tag'] = unserialize($goods['goods_tag']);
            if(!empty($goods['goods_nature']))  $goods['goods_nature'] = unserialize($goods['goods_nature']);
            $specification = Db::name('goods_specification')->where(['parent_id'=>'-1'])->select();
            //产品规格详情
            $goods_specification_relation = Db::name('goods_relation_specification')->where(['goods_id'=>$goods['goods_id'],'is_delete'=>'0'])->select();

            $arr = [];    //选中的规格
            foreach ($goods_specification_relation as $v){
                $arr1 = explode(',',$v['specification_ids']);
                foreach ($arr1 as $val){
                    array_push($arr,$val);
                }
            }

            //父级分类
//            $goodsSpecificationBeans = Db::name('goods_relation_specification')->alias('a')
//                ->field('c.specification_id,c.specification_value')
//                ->join('th_goods_specification b', 'FIND_IN_SET(b.specification_id,a.specification_ids)')
//                ->join('th_goods_specification c', 'b.parent_id=c.specification_id')
//                ->where(['a.is_delete' => '0', 'a.goods_id' => $goods['goods_id'], 'b.is_delete' => '0', 'c.is_delete' => '0'])
//                ->group('c.specification_id')
//                ->select();
            $beans = Db::name('goods_relation_specification')->where(['goods_id'=>$goods['goods_id'],'is_delete'=>'0'])->order("specification_id desc")->value('specification_ids');
            $beans = explode(',',$beans);
            $goodsSpecificationBeans = array();
            foreach ($beans as $v){
                $arr1 = Db::name('goods_specification')->alias('a')
                    ->field('b.specification_id,b.specification_value')
                    ->join('th_goods_specification b','a.parent_id = b.specification_id')
                    ->where(['a.specification_id'=>$v])
                    ->find();
                if(!empty($arr1)) {
                    if (!in_array($arr1, $goodsSpecificationBeans)) {
                        array_push($goodsSpecificationBeans, $arr1);
                    }
                }
            }

            foreach ($goodsSpecificationBeans as &$v){
                $v['specification'] = Db::name('goods_specification')->where(['parent_id'=>$v['specification_id'],'merchants_id'=>['in',['0',$merchant['member_id']]]])->select();
            }

            $seed_class = Db::name('goods_class')->where(['parent_id'=>$goods['parent_class']])->select();

            $this->assign(['parent_class'=>$parent_class,'brand'=>$brand,'re'=>$goods,
                'specification'=>$specification,'goods_specification_relation'=>$goods_specification_relation]);
            $this->assign(['arr'=>$arr,'goodsSpecificationBeans'=>$goodsSpecificationBeans,'seed_class'=>$seed_class]);
            return $this->fetch('goods/insert_goods');
        }
    }

    /**
     *查找分类
     */
    public function querySpecification(){
        //if(Request::instance()->isAjax()) {
            $specification_id = input('id');
            $list = Db::name('goods_specification')->where(['parent_id'=>$specification_id])->select();
            if(empty($list)){
                error("该规格暂无数据，请自行添加或更换另一个");
            }else{
                $value = Db::name('goods_specification')->where(['specification_id'=>$specification_id])->value('specification_value');
                success(['value'=>$value,'list'=>$list]);
            }
        //}
    }


    /**
     *@商品上移排序
     */
    public function plus_goods_sort(){
        if(Request::instance()->isAjax()){
            $merchant = $this->merchant;
            $goods_id = input('goods_id');
            $name = input('name'); //
            //$goods_state = input('goods_state'); //
            $parent_class = input('parent_class'); // 获取所有的post变量（原始数组）
            $seed_class = input('seed_class'); // 获取所有的post变量（原始数组）
            !empty($name) &&  $map['goods_name'] = ['like', '%' . $name . '%'];
            //!empty($goods_state) && $map['goods_state'] = $goods_state;
            !empty($goods_state) && $map['goods_state'] = $goods_state;
            !empty($parent_class) && $map['parent_class'] = $parent_class;
            !empty($seed_class) && $map['seed_class'] = $seed_class;
            $check = Db::name('goods')->where(['goods_id'=>$goods_id])->find();
            $map['sort'] = ['gt',$check['sort']];
            $map['goods_state'] = 1;
            $map['is_delete'] = '0';
            $map['merchants_id'] = $merchant['member_id'];
            if($check['goods_state'] != '1')        error("请先上架再操作");

            $last = Db::name('goods')->where($map)
                ->order("sort asc,create_time desc")->limit(1)->select();
            if(empty($last)){
                error('商品不能移动');
            }else{
                $sort = $last[0]['sort'];
                $result = Db::name('goods')->where(['goods_id'=>$goods_id])->update(['sort'=>$sort]);
                Db::name('goods')->where(['goods_id'=>$last[0]['goods_id']])->update(['sort'=>$check['sort']]);
            }
            if($result){
                success('操作成功');
            }else{
                error('操作失败');
            }
        }
    }

    /**
     *@下移排序
     */
    public function minus_goods_sort(){
        if(Request::instance()->isAjax()){
            $merchant = $this->merchant;
            $goods_id = input('goods_id');
            $name = input('name'); //
            //$goods_state = input('goods_state'); //
            $parent_class = input('parent_class'); // 获取所有的post变量（原始数组）
            $seed_class = input('seed_class'); // 获取所有的post变量（原始数组）
            !empty($name) &&  $map['goods_name'] = ['like', '%' . $name . '%'];
            //!empty($goods_state) && $map['goods_state'] = $goods_state;
            !empty($goods_state) && $map['goods_state'] = $goods_state;
            !empty($parent_class) && $map['parent_class'] = $parent_class;
            !empty($seed_class) && $map['seed_class'] = $seed_class;
            $check = Db::name('goods')->where(['goods_id'=>$goods_id])->find();
            $map['sort'] = ['lt',$check['sort']];
            $map['goods_state'] = 1;
            $map['is_delete'] = '0';
            $map['merchants_id'] = $merchant['member_id'];
            if($check['goods_state'] != '1')        error("请先上架再操作");

            $last = Db::name('goods')->where($map)
                ->order("sort desc,create_time desc")->limit(1)->select();
            if(empty($last)){
                error('商品不能移动');
            }else{
                $sort = $last[0]['sort'];
                $result = Db::name('goods')->where(['goods_id'=>$goods_id])->update(['sort'=>$sort]);
                Db::name('goods')->where(['goods_id'=>$last[0]['goods_id']])->update(['sort'=>$check['sort']]);
            }
            if($result){
                success('操作成功');
            }else{
                error('操作失败');
            }
        }
    }

    /**
     *@商品置顶
     */
    public function top(){
        if(Request::instance()->isAjax()){
            $merchant = $this->merchant;
            $goods_id = input('goods_id');
            $name = input('name'); //
            $class_id = input('class_id');
            //$goods_state = input('goods_state'); //
            $parent_class = input('parent_class'); // 获取所有的post变量（原始数组）
            $seed_class = input('seed_class'); // 获取所有的post变量（原始数组）
            !empty($name) &&  $map['goods_name'] = ['like', '%' . $name . '%'];
            //!empty($goods_state) && $map['goods_state'] = $goods_state;
            !empty($goods_state) && $map['goods_state'] = $goods_state;
            !empty($parent_class) && $map['parent_class'] = $parent_class;
            !empty($seed_class) && $map['seed_class'] = $seed_class;
            //!empty($goods_state) && $map['goods_state'] = $goods_state;
            //!empty($class_id) && $map[] = ['exp','FIND_IN_SET('.$class_id.',goods_class)'];
            //$map['goods_state'] = 1;
            $map['is_delete'] = '0';
            $map['merchants_id'] = $merchant['member_id'];
            $check = Db::name('goods')->where(['goods_id'=>$goods_id])->find();
            if($check['goods_state'] != '1')        error("请先上架再操作");
            $map['sort'] = ['gt',$check['sort']];
            $last_goods = Db::name('goods')->where($map)
                ->order("sort desc,create_time asc")->limit(1)->select();
            if(empty($last_goods)){
                error('商品不能移动');
            }else{
                $sort = $last_goods[0]['sort']+1;
                $result = Db::name('goods')->where(['goods_id'=>$goods_id])->update(['sort'=>$sort]);
            }
            if($result){
                success("操作成功");
            }else{
                error('操作失败');
            }
        }
    }

    /**
     *@商品置后
     */
    public function after(){
        if(Request::instance()->isAjax()){
            $merchant = $this->merchant;
            $goods_id = input('goods_id');
            $name = input('name'); //
            $parent_class = input('parent_class'); // 获取所有的post变量（原始数组）
            $seed_class = input('seed_class'); // 获取所有的post变量（原始数组）
            !empty($name) &&  $map['goods_name'] = ['like', '%' . $name . '%'];
            //!empty($goods_state) && $map['goods_state'] = $goods_state;
            !empty($goods_state) && $map['goods_state'] = $goods_state;
            !empty($parent_class) && $map['parent_class'] = $parent_class;
            !empty($seed_class) && $map['seed_class'] = $seed_class;
            !empty($class_id) && $map[] = ['exp','FIND_IN_SET('.$class_id.',goods_class)'];
            $map['goods_state'] = 1;
            $map['is_delete'] = '0';
            $map['merchants_id'] = $merchant['member_id'];
            $check = Db::name('goods')->where(['goods_id'=>$goods_id])->find();
            if($check['goods_state'] != '1')        error("请先上架再操作");
            $map['sort'] = ['lt',$check['sort']];
            $last_goods = Db::name('goods')->where($map)
                ->order("sort asc,create_time asc")->limit(1)->select();
            if(empty($last_goods)){
                error('商品不能移动');
            }else{
                $sort = $last_goods[0]['sort']-1;
                $result = Db::name('goods')->where(['goods_id'=>$goods_id])->update(['sort'=>$sort]);
            }
            if($result){
                success('操作成功');

            }else{
                error('操作失败');
            }
        }
    }

    /**
     *分类联动
     */
    public function get_seed_class(){
        if(Request::instance()->isAjax()){
            $parent = input('parent');
            $seed = Db::name('goods_class')->where(['parent_id'=>$parent,'is_delete'=>'0'])->select();
            $option= "<option value=''>选择二级分类</option>";
            if(!empty($seed)){
                foreach($seed as $k=>$v){
                    $option.="<option value=".$v['class_id'].">".$v['class_name']."</option>";
                }
            }else{
                $option= "<option value=''>暂无二级分类</option>";
            }
            echo $option;
        }
    }

    /**
     *@改变商品的上架信息
     */
    public function change_goods_status(){
        if(Request::instance()->isAjax()){
            $id = input('id');
            $status = Db::name('Goods')->where(['goods_id'=>$id])->value('goods_state');
            $abs = 3 - $status;
            $arr = ['1','2'];
            $result = Db::name('Goods')->where(['goods_id'=>$id])->update(['goods_state'=>$abs]);
            if($result){
                success($arr[2-$status]);
            }else{
                error('切换状态失败');
            }
        }
    }

    /**
     *删除商品
     */
    public function del_goods(){
        if(Request::instance()->isAjax()) {
            $ids = input('ids');
            if(empty($ids))     error("删除失败");
            $merchant = $this->merchant;
            $map['merchants_id']    =   $merchant['member_id'];
            $map['goods_id']    =   ['in',$ids];
            $model = model('Goods');
            $result = $model->soft_del($map);
        }
    }

    /**
     *删除商品
     */
    public function recovery_goods(){
        if(Request::instance()->isAjax()) {
            $ids = input('ids');
            if(empty($ids))     error("删除失败");
            $merchant = $this->merchant;
            $map['merchants_id']    =   $merchant['member_id'];
            $map['goods_id']    =   ['in',$ids];
            $model = model('Goods');
            $result = $model->recovery_del($map);
        }
    }

    /**
     *复制商品
     */
    public function copy_goods(){
        if(Request::instance()->isAjax()) {
            $id = input('id');
            $goods = Db::name('goods')->where(['goods_id'=>$id])->find();
            unset($goods['goods_id']);
            $goods['goods_state'] = '2';
            $goods['goods_uuid'] = get_guid();
            $goods['code'] = uniqid();
            $goods['create_time'] = date("Y-m-d H:i:s",time());
            $lastId = Db::name('goods')->insertGetId($goods);
            $create_time = date("Y-m-d H:i:s",time());
            if($lastId){
                Db::name('goods')->where(['goods_id'=>$lastId])->update(['sort'=>$lastId]);
//                $goods_class = Db::name('goods_relation_class')->where(['goods_id'=>$id,'is_delete'=>'0'])->select();
//                if(!empty($goods_class)){
//                    foreach ($goods_class as $v){
//                        $newClass[] = [
//                            'class_id' => $v['class_id'],
//                            'goods_id' => $lastId,
//                            'create_time' => $create_time
//                        ];
//                    }
//                    if(!empty($newClass)){
//                        Db::name('goods_relation_class')->insertAll($newClass);
//                    }
//                }

                $specification = Db::name('goods_relation_specification')->where(['goods_id'=>$id,'is_delete'=>'0'])->select();
                if(!empty($specification)){
                    foreach ($specification as $v){
                        $newSpecification[] = [
                            'goods_id' => $lastId,
                            'specification_sku' => $v['specification_sku'],
                            'specification_ids' => $v['specification_ids'],
                            'specification_names'=> $v['specification_names'],
                            'specification_sales'=> $v['specification_sales'],
                            'specification_stock'=> $v['specification_stock'],
                            'specification_img'=> $v['specification_img'],
                            'specification_price'=> $v['specification_price'],
                            'specification_cost_price'=> $v['specification_cost_price'],
                            'specification_sale_price'=> $v['specification_sale_price'],
                            'create_time'=> $create_time

                        ];
                    }
                    if(!empty($newSpecification)){
                        Db::name('goods_relation_specification')->insertAll($newSpecification);
                    }
                }
                success("复制商品成功");
            }else{
                error("复制商品失败");
            }
        }
    }

    public function goods_qrcode(){
        $this->view->engine->layout(false);
        $goods_id = input('goods_id');
        $goods = Db::name('goods')->where(['goods_id'=>$goods_id])->find();
        $this->assign(['goods'=>$goods]);
        return $this->fetch();
    }

    public function _empty(){
        //根据当前控制器名来判断要执行那个城市的操作
        $this->view->engine->layout(false);
        return $this->fetch('common/error');

    }
}
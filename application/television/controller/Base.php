<?php
/**
 * Created by PhpStorm.
 * User: ljy
 * Date: 17/9/22
 * Time: 下午3:17
 */

namespace app\television\controller;


use think\Auth;
use think\captcha\Captcha;
use think\Controller;
use think\Request;
use think\Session;
use think\Db;
use think\Paginator;
use think\Config;

class Base extends Controller
{
    public $television = array();
    public $system = '';
    public function _initialize(){
        header("Content-type: text/html; charset=utf-8");
        header("Access-Control-Allow-Origin: *");
        $nums = array("1"=>"10","2"=>"20","3"=>"30","4"=>"50","5"=>"100","6"=>"150","7"=>"200","8"=>"300","9"=>"500");
        $this->assign('nums',$nums);
        $this->television = Session::get('member');
        if (empty($this->television)){
            $this->redirect('login/login');
        }
        $request = Request::instance();
        $system = Db::name("system")->where(['id'=>1])->find();
        $this->system = $system;
        $nav= config('nav');
        $url = Session::get('url');
        $this->assign(['system'=>$system,'television'=>$this->television,'url'=>$url,'nav'=>$nav]);
    }



    protected function gaussian_blur($srcImg,$savepath=null,$savename=null,$blurFactor=3){
        $gdImageResource=$this->image_create_from_ext($srcImg);
        $srcImgObj=$this->blur($gdImageResource,$blurFactor);
        $temp = pathinfo($srcImg);
        $name = $temp['basename'];
        $path = $temp['dirname'];
        $exte = $temp['extension'];
        $savename = $savename ? $savename : $name;
        $savepath = $savepath ? $savepath : $path;
        $savefile = $savepath .'/'. $savename;
        $srcinfo = @getimagesize($srcImg);
        switch ($srcinfo[2]) {
            case 1: imagegif($srcImgObj, $savefile); break;
            case 2: imagejpeg($srcImgObj, $savefile); break;
            case 3: imagepng($srcImgObj, $savefile); break;
            default: return '保存失败'; //保存失败
        }

        return $savefile;
        imagedestroy($srcImgObj);
    }

    /**
     * Strong Blur
     *
     * @param  $gdImageResource  图片资源
     * @param  $blurFactor          可选择的模糊程度
     *  可选择的模糊程度  0使用   3默认   超过5时 极其模糊
     * @return GD image 图片资源类型
     * @author Martijn Frazer, idea based on http://stackoverflow.com/a/20264482
     */
    protected function blur($gdImageResource, $blurFactor = 3)
    {
        // blurFactor has to be an integer
        $blurFactor = round($blurFactor);

        $originalWidth = imagesx($gdImageResource);
        $originalHeight = imagesy($gdImageResource);

        $smallestWidth = ceil($originalWidth * pow(0.5, $blurFactor));
        $smallestHeight = ceil($originalHeight * pow(0.5, $blurFactor));

        $prevImage = $gdImageResource;
        $prevWidth = $originalWidth;
        $prevHeight = $originalHeight;

        for($i = 0; $i < $blurFactor; $i += 1)
        {
            // determine dimensions of next image
            $nextWidth = $smallestWidth * pow(2, $i);
            $nextHeight = $smallestHeight * pow(2, $i);

            // resize previous image to next size
            $nextImage = imagecreatetruecolor($nextWidth, $nextHeight);
            imagecopyresized($nextImage, $prevImage, 0, 0, 0, 0,
                $nextWidth, $nextHeight, $prevWidth, $prevHeight);

            // apply blur filter
            imagefilter($nextImage, IMG_FILTER_GAUSSIAN_BLUR);

            // now the new image becomes the previous image for the next step
            $prevImage = $nextImage;
            $prevWidth = $nextWidth;
            $prevHeight = $nextHeight;
        }

        // scale back to original size and blur one more time
        imagecopyresized($gdImageResource, $nextImage,
            0, 0, 0, 0, $originalWidth, $originalHeight, $nextWidth, $nextHeight);
        imagefilter($gdImageResource, IMG_FILTER_GAUSSIAN_BLUR);

        // clean up
        imagedestroy($prevImage);

        // return result
        return $gdImageResource;
    }

    protected function image_create_from_ext($imgfile)
    {
        $info = getimagesize($imgfile);
        $im = null;
        switch ($info[2]) {
            case 1: $im=imagecreatefromgif($imgfile); break;
            case 2: $im=imagecreatefromjpeg($imgfile); break;
            case 3: $im=imagecreatefrompng($imgfile); break;
        }
        return $im;
    }

    /**
     *判断订单锁定人
     */
    protected function check_order_locker($order_id){
        $order = Db::name('order_merchants')->where(['order_no'=>$order_id])->find();
        $user = session('merchants');
        if($order['is_lock'] == '1' && $user['uname'] !=='admin'){
//			echo json_encode(array('status'=>'cannot','info'=>'你没有权限,无法进行操作!'));
            echo json_encode(array('status'=>'ok'));
            exit;
        }else{
//			if($order['locker_id'] == $user['id'] || $user['uname'] == 'admin'){
//				echo json_encode(array('status'=>'ok'));
//				exit;
//			}else{
//				echo json_encode(array('status'=>'cannot','info'=>'你没有权限,无法进行操作!'));
//				exit;
//			}
            echo json_encode(array('status'=>'ok'));
            die;
        }
    }



}
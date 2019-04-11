<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use DB;
use App\model\Wx\WxUser;
class WxController extends Controller
{
    //第一次调用接口
    public function valid(){
        echo $_GET['echostr'];
    }
//    用户关注微信公众号
    public function wxEvent(){
        $data = file_get_contents("php://input");
        $time=date('Y-m-d H:i:s');
        $str=$time.$data."\n";
        is_dir('logs') or mkdir('logs',0777,true);
        file_put_contents("logs/wx_event.log",$str,FILE_APPEND);

        $obj=simplexml_load_string($data);
        $wx_id=$obj->ToUserName;
        $event=$obj->Event;
        $openid=$obj->FromUserName;
        if($event=='subscribe'){
            $res=WxUser::where(['openid'=>$openid])->first();
            if($res){
                echo '<xml><ToUserName><![CDATA['.$openid.']]></ToUserName><FromUserName><![CDATA['.$wx_id.']]></FromUserName><CreateTime>'.time().'</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA['. '欢迎回来 '. $res['nickname'] .']]></Content></xml>';
            }else{
                $u=$this->WxUserTail($obj->FromUserName);
                $info=[
                    'openid'=>$u['openid'],
                    'nickname'=>$u['nickname'],
                    'sex'=>$u['sex'],
                    'city'=>$u['city'],
                    'province'=>$u['province'],
                    'country'=>$u['country'],
                    'headimgurl'=>$u['headimgurl'],
                    'subscribe_time'=>$u['subscribe_time'],
                    'subscribe_scene'=>$u['subscribe_scene']
                ];
                $id=WxUser::insertGetId($info);
                echo '<xml><ToUserName><![CDATA['.$openid.']]></ToUserName><FromUserName><![CDATA['.$wx_id.']]></FromUserName><CreateTime>'.time().'</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA['. '欢迎关注 '. $u['nickname'] .']]></Content></xml>';
            }
        }
//        return $this->zi();
    }

    //获取access_token
    public function access_token(){

        $key="access_token";
//        Redis::flush();
        $token=Redis::get($key);
        if(!$token){
            $url="https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".env('APPID')."&secret=".env('SECRET');
            $access_token=file_get_contents($url);
            $arr=json_decode($access_token);
            $token=$arr->access_token;
            Redis::set($key,$token);
            Redis::expire($key,3600);
        }
        return $token;
    }
    //查询用户资料
    public function WxUserTail($openid){
        $data=file_get_contents("https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$this->access_token()."&openid=".$openid."&lang=zh_CN");
        $arr=json_decode($data,true);
        return $arr;
    }
    public function Zi(){
        $objtaken = new \Url();
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$this->access_token();
        $arr=array(
            "button"=>array(
                array(
                    'name'=>"玩具",
                    'sub_button'=>array(
                        array(
                            'name'=>"拍照",
                            "type"=>"pic_sysphoto",
                            "key"=>"tf",
                        ),
                        array(
                            'name'=>"关联",
                            "type"=>"view",
                            "key"=>"cgf",
                            "url"=>"http://mp.weixin.qq.com"
                        )
                    ),
                ),
                array(
                    "name"=>"菜单",
                    "sub_button"=>array(
                        array(
                            'name'=>"男娃娃",
                            "type"=>"click",
                            "key"=>"xxx",
                        ),
                        array(
                            'name'=>"女娃娃",
                            "type"=>"click",
                            "key"=>"xxx",
                        ),
                        array(
                            'name'=>"小洋人",
                            "type"=>"click",
                            "key"=>"xxx",
                        ),
                    ),
                ),

                array(
                    "name"=>"推广",
                    "sub_button"=>array(
                        array(
                            'name'=>"地址",
                            "type"=>"scancode_push",
                            "key"=>"ss",
                        ),
                    ),
                )
            ),
        );
        $arrinfo = json_encode($arr,JSON_UNESCAPED_UNICODE);
// var_dump($arrinfo);

        $bol = $objtaken->sendPost($url,$arrinfo);
        var_dump($bol);
    }

}

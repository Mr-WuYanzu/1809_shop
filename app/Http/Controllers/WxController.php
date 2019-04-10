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
//        dd($data);
        $obj=simplexml_load_string($data);
//        dd($obj);
        $u=$this->WxUserTail($obj->FromUserName);
//        dd($u);
        $openid=$u['openid'];
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
        $res=WxUser::where('openid',$openid)->get();
        if($res){
            $id=WxUser::insertGetId($info);
            echo "<xml>
                  <ToUserName><![CDATA[toUser]]>".$openid."</ToUserName>
                  <FromUserName><![CDATA[fromUser]]>wxe5ff29e2590e9cef</FromUserName>
                  <CreateTime>".time()."</CreateTime>
                  <MsgType><![CDATA[text]]></MsgType>
                  <Content><![CDATA[欢迎关注，".$u['nickname']."]]></Content>
                </xml>";
        }

        dd($id);

        $time=date('Y-m-d H:i:s');
        $str=$time.$data."\n";
        is_dir('logs') or mkdir('logs',0777,true);
        file_put_contents("logs/wx_event.log",$str,FILE_APPEND);
        echo "SUCCESS";
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

}

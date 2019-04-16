<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use DB;
use App\model\Wx\WxUser;
use App\model\Wx\WxText;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Facades\Storage;
class WxController extends Controller
{
    //第一次调用接口
    public function valid(){
        echo $_GET['echostr'];
    }
//    用户关注微信公众号
    public function wxEvent(){
        $client=new Client();
        $data = file_get_contents("php://input");
        $time=date('Y-m-d H:i:s');
        $str=$time.$data."\n";
        is_dir('logs') or mkdir('logs',0777,true);
        file_put_contents("logs/wx_event.log",$str,FILE_APPEND);
        $obj=simplexml_load_string($data);
        $wx_id=$obj->ToUserName;
        $openid=$obj->FromUserName;
        $type=$obj->MsgType;
        if($type=='text'){              //文字消息入库
            $font=$obj->Content;
            $time=$obj->CreateTime;
            $info=[
                    'type'=>'text',
                    'openid'=>$openid,
                    'create_time'=>$time,
                    'font'=>$font
                ];
            $id=WxText::insertGetId($info);


            //获取天气信息
            if(strpos($obj->Content,'+天气')){
                $city=explode('+',$obj->Content)[0];
                $url="https://free-api.heweather.net/s6/weather/now?parameters&location=".$city."&key=HE1904161030301545";
                $arr=json_decode(file_get_contents($url),true);
                if($arr['HeWeather6'][0]['status']!=='ok'){
                    echo "<xml>
                              <ToUserName><![CDATA[".$openid."]]></ToUserName>
                              <FromUserName><![CDATA[".$wx_id."]]></FromUserName>
                              <CreateTime>".time()."</CreateTime>
                              <MsgType><![CDATA[text]]></MsgType>
                              <Content><![CDATA[城市信息有误]]></Content>
                          </xml>";
                }else{
                   $city=$arr['HeWeather6'][0]['basic']['parent_city'];
                    $cond_txt=$arr['HeWeather6'][0]['now']['cond_txt'];
                    $fl=$arr['HeWeather6'][0]['now']['fl'];
                    $tmp=$arr['HeWeather6'][0]['now']['tmp'];
                    $wind_dir=$arr['HeWeather6'][0]['now']['wind_dir'];
                    $wind_sc=$arr['HeWeather6'][0]['now']['wind_sc'];
                    $wind_spd=$arr['HeWeather6'][0]['now']['wind_spd'];

                    $str="城市:".$city."\n"."天气状况:".$cond_txt."\n"."体感温度:".$fl."\n"."温度:".$tmp."\n"."风向:".$wind_dir."\n"."风力:".$wind_sc."\n"."风速:".$wind_spd."公里/小时"."\n";
                    // echo $str;die;
                    echo "<xml>
                              <ToUserName><![CDATA[".$openid."]]></ToUserName>
                              <FromUserName><![CDATA[".$wx_id."]]></FromUserName>
                              <CreateTime>".time()."</CreateTime>
                              <MsgType><![CDATA[text]]></MsgType>
                              <Content><![CDATA[".$str."]]></Content>
                          </xml>";
                }

            }
        }elseif($type=='image'){        //图片消息入库
            $time=$obj->CreateTime;
            $media_id=$obj->MediaId;
            $url="https://api.weixin.qq.com/cgi-bin/media/get?access_token=".$this->access_token()."&media_id=".$media_id;
            $img=$client->get(new Uri($url));
            //获取文件类型
            $headers=$img->getHeaders();
            $img_name=$headers['Content-disposition'][0];
            $fileInfo=substr($img_name,'-15');
            $img_name=substr(md5(time().mt_rand(1111,9999)),5,8).$fileInfo;
            $img_name=rtrim($img_name,'"');
            // 保存文件
            $res=Storage::put('weixin/img/'.$img_name, $img->getBody());
            var_dump($res);die;
            if($res){
                //文件路径入库
                $data=[
                    'type'=>'img',
                    'openid'=>$openid,
                    'create_time'=>$time,
                    'font'=>$img_name
                ];
                $id=WxText::insertGetId($data);
                if(!$id){
                    Storage::delete('weixin/img/'.$img_name);
                    echo "添加失败";
                }else{
                    echo "添加成功";
                }
            }else{
                echo "添加失败";
            }
        }elseif($type=='voice'){        //语音消息入库
            $time=$obj->CreateTime;
            $media_id=$obj->MediaId;
            $url="https://api.weixin.qq.com/cgi-bin/media/get?access_token=".$this->access_token()."&media_id=".$media_id;
            $voice=$client->get(new Uri($url));
            //获取文件类型
            $headers=$voice->getHeaders();
            $voice_name=$headers['Content-disposition'][0];
            $fileInfo=substr($voice_name,'-15');
            $voice_name=substr(md5(time().mt_rand(1111,9999)),5,8).$fileInfo;
            $voice_name=rtrim($voice_name,'"');
            //保存文件
            $res=Storage::put('weixin/voice/'.$voice_name, $voice->getBody());
            if($res){
                //文件路径入库
                $data=[
                    'type'=>'voice',
                    'openid'=>$openid,
                    'create_time'=>$time,
                    'font'=>$voice_name
                ];
                $id=WxText::insertGetId($data);
                if(!$id){
                    Storage::delete('weixin/voice/'.$voice_name);
                    echo "添加失败";
                }else{
                    echo "添加成功";
                }
            }else{
                echo "添加失败";
            }
        }elseif($type=='event'){
            $event=$obj->Event;
            if($event=='subscribe'){
                $res=WxUser::where(['openid'=>$openid])->first();
                if($res){
                    echo '<xml><ToUserName><![CDATA['.$openid.']]></ToUserName><FromUserName><![CDATA['.$wx_id.']]></FromUserName><CreateTime>'.time().'</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA['. '欢迎回来 '. $res['nickname'] .']]></Content></xml>';
                }else{
                    $u=$this->WxUserTail($obj->FromUserName);
                    dd($u);
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
   //创建微信二级菜单
    public function create_menu(){
        $url="https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$this->access_token();
        $arr=[
            'button'=>[
                [
                    'type'=>'click',
                    'name'=>'贪玩蓝月',
                    'key'=> 'V1001_TODAY_TWLY',
                ],
                [
                    'type'=>'click',
                    'name'=>'决战沙城',
                    'key'=> 'V1001_TODAY_JZSC',
                ]
            ]
        ];
        $str=json_encode($arr,JSON_UNESCAPED_UNICODE);
        $client=new Client();
        $respons=$client->request('POST',$url,[
            'body'=>$str
        ]);
        $ass=$respons->getBody();
        $ar=json_decode($ass,true);
        if($ar['errcode']>0){
            echo "创建菜单失败";
        }else{
            echo "创建菜单成功";
        }
    }

}

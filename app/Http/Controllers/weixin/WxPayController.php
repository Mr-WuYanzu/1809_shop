<?php

namespace App\Http\Controllers\weixin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\weixin\WXBizDataCryptController;
use Illuminate\Support\Str;
class WxPayController extends Controller
{
	public $weixin_unifiedorder_url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
    public $weixin_notify_url = 'http://1809zhanghaibo.comcto.com/weixin/pay/notify';
    public $values=[];
    //微信下单接口
    public function test(){
    	$total_fee=1;
    	$order_id=mt_rand(11111,99999).Str::random(6);
    	$info=[
    		'appid'		=>	env('WEIXIN_APPID_0'),
    		'mch_id'	=>	env('WEIXIN_MCH_ID'),
    		'nonce_str'	=>	Str::random(16),
    		'sign_type'	=>	'MD5',
    		'body'		=>'测试订单号：'.$order_id,
    		'out_trade_no'	=>	$order_id,
    		'total_fee'	=>	$total_fee,
    		'spbill_create_ip'	=>	$_SERVER['REMOTE_ADDR'],
    		'notify_url'	=> 	$this->weixin_notify_url,
    		'trade_type'	=> 'NATIVE'
    	];
    	$this->values=$info;
    	$this->SetSign();
    	// dd($this->values);
    	$xml=$this->toxml();
    	$res = $this->postXmlCurl($xml, $this->weixin_unifiedorder_url, $useCert = false, $second = 30);
    	// dd($res);
    	$obj=simplexml_load_string($res);
    	$data=[
    		'code_url'=>$obj->code_url
    	];
    	return view('weixin.test',$data);
    }
    //回调地址
    public function notify_url(){
    	$data=file_get_contents('php://input');
    	file_put_contents('logs/wx_pay.logs',$data);
    }
    //将数据转换为xml形式
    public function toxml(){
    	if(!is_array($this->values)||count($this->values)<=0){
    		die('数据格式异常');
    	}
    	$xml='<xml>';
    	foreach($this->values as $k=>$v){
    		if(is_numeric($v)){
    			$xml .= '<'.$k.'>'.$v.'</'.$k.'>';
    		}else{
    			$xml .= '<'.$k.'><![CDATA['.$v.']]></'.$k.'>';
    		}
    	}
    	$xml.='</xml>';
    	return $xml;
    }
    private  function postXmlCurl($xml, $url, $useCert = false, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
//		if($useCert == true){
//			//设置证书
//			//使用证书：cert 与 key 分别属于两个.pem文件
//			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
//			curl_setopt($ch,CURLOPT_SSLCERT, WxPayConfig::SSLCERT_PATH);
//			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
//			curl_setopt($ch,CURLOPT_SSLKEY, WxPayConfig::SSLKEY_PATH);
//		}
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if($data){
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            die("curl出错，错误码:$error");
        }
    }
    //生成签名
    public function SetSign(){
    	$sign=$this->makeSign();
    	$this->values['sign']=$sign;
    	return $sign;
    }
    //制作签名
    public function makeSign(){
    	//第一步,排序签名,对参数按照key=value的格式，并按照参数名ASCII字典序排序
    	Ksort($this->values);
    	$str=$this->ToUrlParams();
    	//第二步,拼接API密钥并加密
    	$sign_str=$str.'&key='.env('WEIXIN_MCH_KEY');
    	$sign=MD5($sign_str);
    	//第三步,将所有的字符转换为大写
    	$string=strtoupper($sign);
    	return $string;
    }
    public function ToUrlParams(){
    	$str='';
    	foreach($this->values as $k=>$v){
    		if($k!='sign'&&$v!=''&&!is_array($v)){
    			$str .= $k.'='.$v.'&';
    		}
    	}
    	$str=trim($str,'&');
    	return $str;
    }

}

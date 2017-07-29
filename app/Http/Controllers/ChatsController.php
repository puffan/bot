<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller;
use Illuminate\Http\Request;
use \Curl\Curl;

class ChatsController extends Controller
{
	public function chat(Request $request) {
		$resources = array(
		    "hello" => "world",
		    "谢谢" => "不客气",
		    "打开百度" => "http://www.baidu.com",
	    );

    	$this->validate($request, [
	        'from' => 'required',
	        'to' => 'required',
	        'content' => 'required'
    	]);

		$from = $request->input('from');
    	$to = $request->input('to');
    	$content = $request->input("content");
    	
    	$intent = $this->getIntentService($content);

    	if('找专家' == $intent){
    		return $this->sendP2PMsgToIMService($to, $from, $content);
    	}
    	
    	return $intent;
    }

    public function getIntent(Request $request){
    	return $this->getIntentService($request->input('content'));
    }

    private function getIntentService($content){
    	$topScoringIntent = "None";

    	$curl = new Curl();
    	$curl->setOpt ( CURLOPT_SSL_VERIFYPEER, false );
		$curl->get('https://southeastasia.api.cognitive.microsoft.com/luis/v2.0/apps/a2367e9b-eb53-428f-ab39-6649d7e67476', array(
			'subscription-key' => '47dfda8c60264d78901d53b9bf20e563',
		    'q' => $content,
		    'verbose' => 'true',
		    'timezoneOffset' => '0'
		));

		$result = $curl->response;
		$curl->close();
		$topScoringIntent = $result->topScoringIntent->intent;

		if( !isset($topScoringIntent) || $topScoringIntent == 'None' ){
			$topScoringIntent = "Beyond my understanding!";
		}
		return $topScoringIntent;
    }

    public function sendMsgToIM(Request $request){
    	$from = $request->input('from');
    	$to = $request->input('to');
    	$content = $request->input('content');

    	return $this->sendP2PMsgToIMService($from, $to, $content);
    }

    private function sendP2PMsgToIMService($from, $to, $content){
    	$data = array(
		    'senderAccount' => $from,
		    'targetAccount' => $to,
		    'msgType' => '0',
		    'appID' => '3',
		    "content" => $this->buildIMConent($content),
		);

		$curl = new Curl();
		$curl->setHeader('Content-Type', 'application/json');
		$curl->post('http://58.213.108.45:7801/esdk/rest/ec/eserver/im', $data);
		return json_encode($curl->response);
    }

    private function buildIMConent($content){
    	$template = '<imbody><imagelist/><html/><content>{{content}}</content></imbody>';
    	$result = str_replace("{{content}}",$content, $template);


    	$result = str_replace("<","&lt;", $result);
    	$result = str_replace(">","&gt;", $result);
    	$result = base64_encode($result);

    	return $result;
    }
}

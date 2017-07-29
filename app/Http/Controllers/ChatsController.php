<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller;
use Illuminate\Http\Request;
use \Curl\Curl;

class ChatsController extends Controller
{
	const ESERVER_BASE_URL = 'http://58.213.108.45:7801/esdk/rest/ec/eserver';

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


    /**
     * get intent
     *
     */
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


    /**
     * send im p2p msg
     */
    private function sendP2PMsgToIMService($from, $to, $content){
    	$data = array(
		    'senderAccount' => $from,
		    'targetAccount' => $to,
		    'msgType' => '0',
		    'senderType' => 0,
		    'appID' => '3',
		    "content" => $this->buildIMConent($content),
		);

		$curl = new Curl();
		$curl->setHeader('Content-Type', 'application/json');
		$curl->post(self::ESERVER_BASE_URL.'/im', $data);
		return json_encode($curl->response);
    }

    public function createIMGroup(Request $request){
    	$groupName = $request->input('group_name');
    	$inviteList = array('z00187187');
    	$owner = $request->input('owner');

    	return $this->createIMGroupService($groupName, $inviteList, $owner);
    }

    /**
     * create im group
     *
     */
    private function createIMGroupService($groupName, $inviteList, $owner){
    	$endpoint = self::ESERVER_BASE_URL.'/group';

    	$data = array(
		    'groupName' => $groupName,
		    'inviteList' => $inviteList,
		    'owner' => $owner,
		    'appID' => '3',
		);

		var_dump(json_encode($data));

		$curl = new Curl();
		$curl->setHeader('Content-Type', 'application/json');
		$curl->post($endpoint, $data);
		$curl->close();
		return json_encode($curl->response);
    }

    public function sendIMGroupCard(Request $request){
    	$from = $request->input('from');
    	$groupName = $request->input('group_name');
    	$groupId = $request->input('group_id');

    	return $this->sendIMGroupCardService($from, $groupName, $groupId);
    }

    /*
     * send im card
     */
    private function sendIMGroupCardService($from, $groupName, $groupId){
    	$template = '<![CDATA[<imbody><content>{"cardContext":{"handlerUriAndroid":"{{GROUPID}}","handlerUriIOS":"{{GROUPID}}","isPCDisplay":"1","sourceUrl":""},"cardType":100,"digest":"","imgUrl":"","source":"","title":"{{GROUPNAME}}"}</content></imbody>]]>';

        $content = str_replace("{{GROUPID}}", $groupId, $template);
        $content = str_replace("{{GROUPNAME}}", $groupName, $template);

    	$data = array(
		    'senderAccount' => $from,
		    'senderType' => 0,
		    'msgType' => '2',
		    'appID' => '3',
		    "content" => $this->buildIMConent($content),
		);

		$curl = new Curl();
		$curl->setHeader('Content-Type', 'application/json');
		$curl->post(self::ESERVER_BASE_URL.'/im', $data);
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

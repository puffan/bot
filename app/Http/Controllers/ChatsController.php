<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller;
use Illuminate\Http\Request;
use \Curl\Curl;
use \Cache;

class ChatsController extends Controller
{
	const ESERVER_BASE_URL = 'http://58.213.108.45:7801/esdk/rest/ec/eserver';
	const MS_INTENT_URL = 'https://southeastasia.api.cognitive.microsoft.com/luis/v2.0/apps/a2367e9b-eb53-428f-ab39-6649d7e67476';
	const MS_KB_URL = 'https://westus.api.cognitive.microsoft.com/qnamaker/v2.0/knowledgebases/6243a453-88d2-4bce-a855-be626041b9ee/generateAnswer';

	const ESPACE_GROUP_NAME_PREFIX = '[WeCloud-Experts-Online] ';
	const LAST_WORDS = '我只诞生了3天，还不太明白您的意思，我为WeCloud战队加油!';

	public static $greeting_array = array(
		'您好,今天天气不错，我可以为你做点什么呢？',
		'您好，欢迎再次光临，您可以输入类似‘找专家’、‘XX技术方案’或者语音来向我求助！',
		'您好，华为2017-HC大会即将召开，想咨询什么我可以做向导。',
	);

	const INTENT_FIND_EXPERTS = '找专家';
	const INTENT_FIND_KNOWLEDGE = '找知识';
	const INTENT_GREETING = '打招呼';

	const CACHE_KEY_PREFIX = "BOT";

	public function chat(Request $request) {
		$expertsPool = array(
		    "z00187187",
	    );

    	$this->validate($request, [
	        'from' => 'required',
	        'to' => 'required',
	        'content' => 'required'
    	]);

		$from = $request->input('from');
    	$to = $request->input('to');
    	$content = $request->input("content");

    	$greeting = $request->input("greeting");
    	if( isset($greeting) && $greeting === true ){
    		return $this->sendP2PMsgToIMService($to, $from, $this->randomItemInArray(ChatsController::$greeting_array));
    	}
    	
    	$intent = $this->getIntentService($content);

    	if(self::INTENT_FIND_EXPERTS == $intent){
    		// create group
    		$newGroupName = self::ESPACE_GROUP_NAME_PREFIX.$from.'@'.time();
    		$newGroupId = $this->createIMGroupService($newGroupName, $expertsPool, $from);
    		// send group card
    		$this->sendIMCardService($to, $from, $newGroupName, $newGroupId);
    	}else if(self::INTENT_GREETING == $intent){
    		// TODO:say hello
    		return $this->sendP2PMsgToIMService($to, $from, $this->randomItemInArray(ChatsController::$greeting_array));
    	}else{
    		$answer = $this->getKBService($content);

    		if( isset($answer) ){
    			return $this->sendP2PMsgToIMService($to, $from, $answer);
    		}else{
    			return $this->sendP2PMsgToIMService($to, $from, self::LAST_WORDS);
    		}
    	}

    	// else if(self::INTENT_FIND_KNOWLEDGE == $intent){
    	// 	// TODO:create knowledge card
    	// }
    	return $intent;
    }

    /**
     * get intent
     *
     */
    private function getIntentService($content){
    	$cache_key_intent = self::CACHE_KEY_PREFIX.'_INTENT_'.md5($content);
    	$topScoringIntent = "None";

    	$intentInCache = Cache::get($cache_key_intent);

    	if( isset($intentInCache) ){
    		$topScoringIntent = $intentInCache;
    	}else{
    		$curl = new Curl();
	    	$curl->setOpt ( CURLOPT_SSL_VERIFYPEER, false );
			$curl->get(self::MS_INTENT_URL, array(
				'subscription-key' => '47dfda8c60264d78901d53b9bf20e563',
			    'q' => $content,
			    'verbose' => 'true',
			    'timezoneOffset' => '0'
			));

			$result = $curl->response;
			$curl->close();
			$topScoringIntent = $result->topScoringIntent->intent;

			if( !isset($topScoringIntent )){
				$topScoringIntent = 'None';
			}

			Cache::put($cache_key_intent, $topScoringIntent, 30);
    	}
		return $topScoringIntent;
    }

    /**
     * get knowledge base
     *
     */
    private function getKBService($content){
    	$cache_key_kb = self::CACHE_KEY_PREFIX.'_KB_'.md5($content);
    	$topAnswer = "None";

    	$answerInCache = Cache::get($cache_key_kb);

    	if( isset($answerInCache) ){
    		$topAnswer = $answerInCache;
    	}else{
    		$curl = new Curl();
	    	$curl->setOpt ( CURLOPT_SSL_VERIFYPEER, false );
	    	$curl->setHeader('Ocp-Apim-Subscription-Key', '2e7945235db74c999a6613af3b76b5f6');
    	    $curl->setHeader('Content-Type', 'application/json');

			$curl->POST(self::MS_KB_URL, array(
			    'question' => $content,
			));

			$result = $curl->response;
			$curl->close();
			$topAnswer = $result->answers[0]->answer;

			if( !isset($topAnswer) ){
				$topAnswer = 'None';
			}
			Cache::put($cache_key_kb, $topAnswer, 30);
    	}

		return $topAnswer;
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

		$curl = new Curl();
		$curl->setHeader('Content-Type', 'application/json');
		$curl->post($endpoint, $data);
		$curl->close();

		$result = json_decode($curl->response->resultContext);
		return $result->groupID;
    }

    /*
     * send im card
     */
    private function sendIMCardService($from, $to, $groupName, $groupId){
    	$template = '<![CDATA[<imbody><content>{"cardContext":{"handlerUriAndroid":"{{GROUPID}}","handlerUriIOS":"{{GROUPID}}","isPCDisplay":"1","sourceUrl":""},"cardType":100,"digest":"","imgUrl":"","source":"","title":"{{GROUPNAME}}"}</content></imbody>]]>';

        $content = str_replace("{{GROUPID}}", $groupId, $template);
        $content = str_replace("{{GROUPNAME}}", $groupName, $content);

    	$data = array(
		    'senderAccount' => $from,
		    'targetAccount' => $to,
		    'senderType' => 0,
		    'msgType' => '0',
		    'appID' => '3',
		    "content" => base64_encode($content),
		    "contentTypeForMobile" => '10',
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

    private function randomItemInArray( $array ){
    	return $array[rand(0, count($array)-1)];
    }

    public function getAnswer(Request $request){
    	return $this->getKBService($request->input('content'));
    }

    public function getIntent(Request $request){
    	return $this->getIntentService($request->input('content'));
    }

    public function sendMsgToIM(Request $request){
    	$from = $request->input('from');
    	$to = $request->input('to');
    	$content = $request->input('content');

    	return $this->sendP2PMsgToIMService($from, $to, $content);
    }

    public function createIMGroup(Request $request){
    	$groupName = $request->input('group_name');
    	$inviteList = array('z00187187');
    	$owner = $request->input('owner');

    	return $this->createIMGroupService($groupName, $inviteList, $owner);
    }

    public function sendIMGroupCard(Request $request){
    	$from = $request->input('from');
    	$groupName = $request->input('group_name');
    	$to = $request->input('to');
    	$groupId = $request->input('group_id');

    	return $this->sendIMCardService($from, $to, $groupName, $groupId);
    }
}

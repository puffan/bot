<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller;
use Illuminate\Http\Request;

class ChatsController extends Controller
{
	public function chat(Request $request) {
		$resources = array(
		    "hello" => "world",
		    "谢谢" => "不客气",
		    "打开百度" => "http://www.baidu.com",
	    );

    	$this->validate($request, [
	        'from' => array('required', 'regex:/^[0-9]{5,8}$/'),
	        'to' => 'required',
	        'content' => 'required'
    	]);

    	$content = $request->input("content");

    	if(array_key_exists($content, $resources)){
    		return $resources[$content];
    	}

    	return "NO DATA FOUND";
    }
}

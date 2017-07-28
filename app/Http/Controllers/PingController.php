<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller;

class PingController extends Controller
{
    public function ping()
    {
    	return "pong";
    }
}

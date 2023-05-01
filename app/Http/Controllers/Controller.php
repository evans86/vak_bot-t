<?php

namespace App\Http\Controllers;

use App\Services\External\BottApi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function home()
    {
        return view('home');
    }

    public function test()
    {
        $result = BottApi::checkUser(
            '398981226',
            '29978beb742581e93e31ec12ac518b76299755483b9614b8',
            '062d7c679ca22cf88b01b13c0b24b057',
            'd75bee5e605d87bf6ebd432a2b25eb0e');
        dd($result);
    }
}

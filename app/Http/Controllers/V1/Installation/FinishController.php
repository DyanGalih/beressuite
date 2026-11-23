<?php

namespace App\Http\Controllers\V1\Installation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FinishController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        \Storage::disk('local')->put('database_created', 'database_created');

        return response()->json(['success' => true]);
    }
}

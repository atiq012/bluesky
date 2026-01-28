<?php

namespace App\Http\Controllers\Admin\HelpDesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }
    public function store(Request $request)
    {
        dd($request->all());
    }

    public function destroy(string $id)
    {
        //
    }
}

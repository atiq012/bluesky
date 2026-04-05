<?php
namespace App\Http\Controllers\Admin\HelpDesk;

// use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestDetailsController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function getRequestDetails($id, Request $request)
    {
        $data = DB::table('requests as req')
            ->where('req.id', '=', $id)
            ->selectRaw('req.id as idd,req.category_id,req.requester_id,req.priority,req.subject,req.description,req.file_path,req.status,req.updated_at,f_username(req.updated_by) as updated_by,f_username(req.created_by) as created_by,req.created_at,req.updated_at,(SELECT name FROM categories WHERE id = req.category_id) as category_name,(SELECT name FROM users WHERE id = req.requester_id) as requester_name,(SELECT name FROM users WHERE id = req.assignee_id) as assigned_to_name', )->first();

        return response()->json($data);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

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
        try {
            // Validate the request data
            $validated = $request->validate([
                'ticketId'             => 'required|integer',
                'note'                 => 'required|string',
                'show_to_assignee'     => 'boolean',
                'send_as_email'        => 'boolean',
                'send_as_notification' => 'boolean',
            ]);
            $to_user_id = DB::table('requests')->where('id', $request->ticketId)->first();
            // dd($to_user_id);

            // Insert the note
            $noteId = DB::table('request_details')->insertGetId([
                'request_id'        => $request->ticketId,
                'note'              => $request->note,
                'from_user_id'      => auth()->id(),
                'to_user_id'        => $to_user_id->requester_id,
                'send_notification' => $request->send_as_notification ?? false,
                'send_email'        => $request->send_as_email ?? false,
                'show_to_assignee'  => $request->show_to_assignee ?? false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            // Get the created note
            $note = DB::table('request_details')->where('id', $noteId)->first();

            return response()->json([
                'success' => true,
                'message' => 'Note submitted successfully',
                'data'    => $note,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add note',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function getRequestDetails($id, Request $request)
    {
        $data = DB::table('requests as req')
            ->where('req.id', '=', $id)
            ->selectRaw('req.id as idd,req.category_id,req.requester_id,req.priority,req.subject,req.description,req.file_path,req.status,req.updated_at,f_username(req.updated_by) as updated_by,f_username(req.created_by) as created_by,req.created_at,req.updated_at,(SELECT name FROM categories WHERE id = req.category_id) as category_name,(SELECT name FROM users WHERE id = req.requester_id) as requester_name,(SELECT name FROM users WHERE id = req.assignee_id) as assigned_to_name,req.request_number')->first();

        $details = DB::table('request_details')
            ->where('request_id', '=', $id)
            ->get();
        $me     = auth()->user();
        $author = DB::table('users')->where('id', $data->requester_id)->first();
        return response()->json(['data' => $data, 'details' => $details, 'me' => $me, 'author' => $author]);
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

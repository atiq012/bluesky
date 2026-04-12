<?php
namespace App\Http\Controllers\Admin\HelpDesk;

use App\Http\Controllers\BaseController;
use App\Models\Helpdesk\Request as HelpdeskRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Yajra\DataTables\DataTables;

class RequestController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = DB::table('requests as req')
            ->selectRaw('req.id as idd,req.request_number as request_number,req.category_id,req.requester_id,req.priority,req.subject,req.description,req.file_path,req.status,req.updated_at,f_username(req.updated_by) as updated_by,f_username(req.created_by) as created_by,req.created_at,req.updated_at,(SELECT name FROM categories WHERE id = req.category_id) as category_name,(SELECT name FROM users WHERE id = req.requester_id) as requester_name,(SELECT name FROM users WHERE id = req.assignee_id) as assigned_to_name', )->get();
        return DataTables::of($data)->addIndexColumn()->make(true);
    }

    private function generateRequestNumber()
    {
                                            // Get current date format: YYMMDD
        $datePrefix = now()->format('ymd'); // 260331

        // Find the latest request number for today
        $lastRequest = HelpdeskRequest::where('request_number', 'LIKE', $datePrefix . '%')
            ->orderBy('request_number', 'desc')
            ->first();

        if ($lastRequest) {
            // Extract the sequence number from the last request
            $lastSequence = (int) substr($lastRequest->request_number, -2);
            $newSequence  = $lastSequence + 1;
        } else {
            $lastRequest = HelpdeskRequest::orderBy('id', 'desc')->first();

            if ($lastRequest == null) {
                // Reset to 01 if sequence exceeds 99
                $newSequence = 1;
            } else {
                $lastSequence = (int) substr($lastRequest->request_number, -2);
                // Start from 01 if no request exists for today
                $newSequence = $lastSequence + 1;
            }

        }

        // Format sequence number with leading zero (01, 02, etc.)
        $sequenceFormatted = str_pad($newSequence, 2, '0', STR_PAD_LEFT);
        // Combine date prefix and sequence
        return $datePrefix . $sequenceFormatted;
    }

    public function store(Request $request)
    {
        // dd($request->all());

        $req_num = $this->generateRequestNumber();

        $requestData                 = new HelpdeskRequest;
        $requestData->request_number = $req_num; // Use the generated request number
        $requestData->category_id    = $request->input('cate_id');
        $requestData->subcategory_id = $request->input('subcate_id');
        $requestData->requester_id   = $request->input('requester');
        $requestData->priority       = $request->input('priority');
        $requestData->subject        = $request->input('subject');
        $requestData->description    = $request->input('description');
        $requestData->request_type   = $request->input('request_type');
        $requestData->asset          = $request->input('assets');

        $requestData->mode        = $request->input('mode');
        $requestData->level       = $request->input('level');
        $requestData->assignee_id = $request->input('assign_to');
        // Handle file upload if exists

        if (($request->hasFile('file_path'))) {

            $request_image = $request->file('file_path');
            $image_name    = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

            $image_path = public_path('/uploads/helpDesk/');
            if (! File::exists($image_path)) {
                File::makeDirectory($image_path, 0777, true);
            }

            $request_image->move($image_path, $image_name);

            $requestData->file_path = '/uploads/helpDesk/' . $image_name;

        }
        $requestData->status     = 'open';
        $requestData->created_by = Auth::user()->id;
        $requestData->save();

        $success = 's';
        return $this->SuccessResponse($success, 'Successfully Request Saved.');

    }

    public function update(Request $request)
    {
        // dd($request->all());
        $id          = $request->input('id');
        $requestData = HelpdeskRequest::find($id);

        if ($requestData) {
            $requestData->category_id    = $request->input('cate_id');
            $requestData->subcategory_id = $request->input('subcate_id');
            $requestData->requester_id   = $request->input('requester');
            $requestData->priority       = $request->input('priority');
            $requestData->subject        = $request->input('subject');
            $requestData->description    = $request->input('description');

            $requestData->request_type = $request->input('request_type');
            $requestData->asset        = $request->input('assets');

            $requestData->mode        = $request->input('mode');
            $requestData->level       = $request->input('level');
            $requestData->assignee_id = $request->input('assign_to');

            // Handle file upload if exists
            if (($request->hasFile('file_path'))) {

                $request_image = $request->file('file_path');
                $image_name    = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

                $image_path = public_path('/uploads/helpDesk/');
                if (! File::exists($image_path)) {
                    File::makeDirectory($image_path, 0777, true);
                }

                if(File::exists(public_path($requestData->file_path))){
                    File::delete(public_path($requestData->file_path));
                }

                $request_image->move($image_path, $image_name);

                $requestData->file_path = '/uploads/helpDesk/' . $image_name;

            }
            $requestData->updated_by = Auth::user()->id;
            $requestData->save();

            $success = 's';
            return $this->SuccessResponse($success, 'Successfully Request Updated.');
        } else {
            return $this->ErrorResponse('Request not found', 404);
        }
    }

    public function edit(Request $request)
    {
        $id = $request->input('id');
        // dd($id);
        $requestData = HelpdeskRequest::find($id);

        if ($requestData) {
            return response()->json($requestData);
        } else {
            return response()->json(['message' => 'Request not found'], 404);
        }
    }

    public function assignRequest(Request $request)
    {
        // dd($request->all());
        $id       = $request->input('idd');
        $assignTo = $request->input('assign_to');

        $requestData = HelpdeskRequest::find($id);

        if ($requestData) {
            $requestData->assignee_id = $assignTo;
            $requestData->status      = 'In Progress';
            $requestData->updated_by  = Auth::user()->id;
            $requestData->save();

            // return response()->json(['message' => 'Request assigned successfully']);
            $success = 's';
            return $this->SuccessResponse($success, 'Request assigned successfully.');
        } else {
            return response()->json(['message' => 'Request not found'], 404);
        }
    }

    public function statusChange(Request $request)
    {
        // dd($request->all());
        $id        = $request->input('idd');
        $newStatus = $request->input('status');

        $requestData = HelpdeskRequest::find($id);

        if ($requestData) {
            $requestData->status     = $newStatus;
            $requestData->updated_by = Auth::user()->id;
            $requestData->save();

            $success = 's';
            return $this->SuccessResponse($success, 'Request assigned successfully.');
        } else {
            return response()->json(['message' => 'Request not found'], 404);
        }
    }

    public function destroy(string $id)
    {
        //
    }
}

<?php
namespace App\Http\Controllers\Admin\ApiManagement;

// use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseController;
use App\Models\APIManagement\ApiManagement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\DataTables;

class APIManagementController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = DB::table('api_management as am')
            ->select('am.id as idd', 'am.name', 'am.author', 'am.email', 'am.password', 'am.branch_code', 'am.application_id', 'am.application_secret', 'am.endpoint', 'am.status','am.created_at','am.updated_at', 'u.name as created_by', 'u2.name as updated_by')
            ->leftJoin('users as u', 'u.id', '=', 'am.created_by')
            ->leftJoin('users as u2', 'u2.id', '=', 'am.updated_by')
            ->get();

        return DataTables::of($data)->addIndexColumn()->make(true);

    }

    public function changeAPIStatus(Request $request)
    {

        $id = $request->id;
        if ($request->id) {

            $area = ApiManagement::where('id', $request->id)->first();
            if ($area->status == 'active') {

                $area->status = 'inactive';
            } else if ($area->status == 'inactive') {
                $area->status = 'active';
            }
            $area->save();
            $success = '';
            return $this->SuccessResponse($success, 'Successfully API status updated.');

        } else {
            $error = 'Id can not be null.';
            return $this->ErrorResponse($error);

        }
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
        // dd($request->all());

        // validation
        $request->validate([
            'name' => 'required',
            'author' => 'required',
            'email' => 'required|email',
            'password' => 'required',
            'branch_code' => 'required',
            'application_id' => 'required',
            'application_secret' => 'required',
            'end_point' => 'required',
        ]);

        $api = new ApiManagement();
        $api->name = $request->name;
        $api->author = $request->author;
        $api->email = $request->email;
        $api->password = Hash::make($request->password);
        $api->branch_code = $request->branch_code;
        $api->application_id = $request->application_id;
        $api->application_secret = $request->application_secret;
        $api->endpoint = $request->end_point;
        $api->status = 'active';
        $api->created_by = auth()->user()->id;
        $api->save();

        $success = '';
        return $this->SuccessResponse($success, 'Successfully API Created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
    public function destroy(Request $request)
    {
        $id = $request->id;
        if ($request->id) {

            $area = ApiManagement::where('id', $request->id)->first();

            $area->delete();
            $success = '';
            return $this->SuccessResponse($success, 'Successfully API Deleted.');

        } else {
            $error = 'Id can not be null.';
            return $this->ErrorResponse($error);

        }
    }
}

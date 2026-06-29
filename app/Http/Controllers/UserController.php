<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Models\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\DataTables;

class UserController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $data = DB::table('users as u')->where('type', 2)
            ->join('roles as r', 'r.id', 'u.user_role')
            ->selectRaw('u.name,u.email,u.img_path as img,u.phone,u.status,r.name as r_name,u.img_path,u.id as idd,u.created_at,u.updated_at,f_department(u.dept_id) as dept,f_designation(u.designation_id) as desg,u.emp_id,f_off_loc(u.office_loc_id) as off_loc,f_username(u.updated_by) as updated_by,f_username(u.created_by) as created_by')->get();
        // rep_user(u.report_to) as rep_to,
        return DataTables::of($data)->addIndexColumn()->make(true);
    }
    public function getAgentExternalUsers()
    {

        $auth = auth()->user();

        $data = DB::table('users as u')->where('type', 2)->where('agent_id', $auth->agent_id)
            // ->join('roles as r', 'r.id', 'u.user_role')
            ->selectRaw('u.name,u.email,u.img_path as img,u.phone,u.status,u.img_path,u.id as idd,u.created_at,u.updated_at,u.dept_id as dept,u.designation_id as desg,u.emp_id,f_username(u.updated_by) as updated_by,f_username(u.created_by) as created_by')->get();

        return DataTables::of($data)->addIndexColumn()->make(true);
    }

    public function getAllUsers()
    {
        $user = DB::table('users')->get();
        return response()->json($user);
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

        $auth = User::where('email', $request->useEmail)->first();
        $validator = validator($request->all(),
            ['name' => 'required'],
            ['phone' => 'required'],
            ['email' => 'required'],
            ['staff_id' => 'required'],
            ['dept_name' => 'required'],
            ['desg' => 'required'],
            ['off_loct' => 'required'],
            ['report_to' => 'required'],
            ['role_id' => 'required'],
        );
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->all(), 'types' => 'e']);
        }
        $user = new User;
        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->email = $request->email;
        $user->emp_id = $request->staff_id;
        $user->dept_id = $request->dept_name;
        $user->designation_id = $request->desg;
        $user->office_loc_id = $request->off_loct;
        $user->report_to = $request->report_to;
        $user->user_role = $request->role_id;

        if ($request->hasFile('profile_picture')) {

            $request_image = $request->file('profile_picture');
            $image_name = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

            $image_path = public_path('/uploads/profile_image/');
            if (!File::exists($image_path)) {
                File::makeDirectory($image_path, 0777, true);
            }

            $request_image->move($image_path, $image_name);
            $user->img_path = '/uploads/profile_image/' . $image_name;

        } else {
            $profilePicturePath = null;
        }

        $user->type = 2;
        $user->is_active = 1;
        $user->status = 1;
        $user->created_by = $auth->id;
        $user->password = Hash::make('Gblue@sky7');
        // $user->agent_id = $auth->agent_id;
        $user->save();
        return response()->json(['message' => 'Successfully User Saved.', 'types' => 's']);

    }
    public function agntUserstore(Request $request)
    {
        // dd($request->all());

        $auth = User::where('email', $request->useEmail)->first();
        $validator = validator($request->all(),
            ['name' => 'required'],
            ['phone' => 'required'],
            ['email' => 'required'],
            ['staff_id' => 'required'],
            ['dept_name' => 'required'],
            // ['desg' => 'required'],
            // ['off_loct' => 'required'],
            // ['report_to' => 'required'],
            // ['role_id' => 'required'],
        );
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->all(), 'types' => 'e']);
        }
        $user = new User;
        $user->name = $request->name;
        $user->email = $request->email;
        $user->emp_id = $request->staff_id;
        $user->phone = $request->phone;
        $user->dept_id = $request->deptment_id;
        $user->designation_id = $request->desg_id;
        // $user->office_loc_id = $request->off_loct;
        // $user->report_to = $request->report_to;
        // $user->user_role = $request->role_id;
        $user->agent_id = $auth->agent_id;

        if ($request->hasFile('profile_picture')) {

            $request_image = $request->file('profile_picture');
            $image_name = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

            $image_path = public_path('/uploads/profile_image/');
            if (!File::exists($image_path)) {
                File::makeDirectory($image_path, 0777, true);
            }

            $request_image->move($image_path, $image_name);
            $user->img_path = '/uploads/profile_image/' . $image_name;

        } else {
            $profilePicturePath = null;
        }

        $user->type = 2;
        $user->is_active = 1; // active
        $user->status = 1; // active
        $user->created_by = $auth->id;
        $user->password = Hash::make('Gblue@sky7');
        // $user->agent_id = $auth->agent_id;
        $user->save();
        return response()->json(['message' => 'Successfully User Saved.', 'types' => 's']);

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
    public function edit(Request $request)
    {
        $data = User::find($request->id);
        return response()->json($data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        // dd($request->all());
        $auth = User::where('email', $request->useEmail)->first();

        $user = User::where('id', $request->user_id)->first();
        $user->name = $request->name ? $request->name : $user->name;
        $user->phone = $request->phone ? $request->phone : $user->phone;
        $user->email = $request->email ? $request->email : $user->email;
        $user->emp_id = $request->staff_id ? $request->staff_id : $user->emp_id;
        $user->dept_id = $request->dept_name ? $request->dept_name : $user->dept_id;
        $user->designation_id = $request->desg ? $request->desg : $user->designation_id;
        $user->office_loc_id = $request->off_loct ? $request->off_loct : $user->office_loc_id;
        $user->report_to = $request->report_to ? $request->report_to : $user->report_to;
        $user->user_role = $request->role_id ? $request->role_id : $user->user_role;

        if ($request->hasFile('profile_picture')) {

            $request_image = $request->file('profile_picture');
            $image_name = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

            $image_path = public_path('/uploads/profile_image/');
            if (!File::exists($image_path)) {
                File::makeDirectory($image_path, 0777, true);
            }

            $request_image->move($image_path, $image_name);
            $user->img_path = '/uploads/profile_image/' . $image_name;

        } else {
            $profilePicturePath = null;
        }

        // $user->type = 1;
        // $user->is_active = 1;
        // $user->status = 1;
        $user->updated_by = $auth->id;

        $user->save();
        return response()->json(['message' => 'Successfully User Upadete.', 'types' => 's']);

    }

    public function statusUpdate(Request $request)
    {
        if ($id = $request->useridStatus) {
            $user = User::where('id', $id)->first();
            $user->status = $request->status;
            $user->save();
            $success='';
            return $this->SuccessResponse($success, 'Successfully User status updated.');

        } else {
            $error = 'Id can not be null.';
            return $this->ErrorResponse($error);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        if ($request->id) {

            $user = User::where('id', $request->id)->first();
            if($user->img_path){
                if ($user->img_path) {

                    $filePath = public_path().$user->img_path;
                    File::delete($filePath);
                }
            }
            $user->delete();
            $success = '';
            return $this->SuccessResponse($success, 'Successfully User Deleted.');

        } else {
            $error = 'Id can not be null.';
            return $this->ErrorResponse($error);

        }
    }
}

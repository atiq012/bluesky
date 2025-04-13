<?php

namespace App\Http\Controllers\Admin\Traveler;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Traveller\Traveller;
use Illuminate\Support\Facades\File;
use DB;
use Yajra\DataTables\DataTables;

class TravelerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // $data = DB::table('users as u')->where('type', 1)
        //     ->join('roles as r', 'r.id', 'u.user_role')
        //     ->selectRaw('u.name,u.email,u.img_path as img,u.phone,u.status,r.name as r_name,u.img_path,u.id as idd,u.created_at,u.updated_at,f_department(u.dept_id) as dept,f_designation(u.designation_id) as desg,u.emp_id,f_off_loc(u.office_loc_id) as off_loc,f_username(u.updated_by) as updated_by,f_username(u.created_by) as created_by')->get();
        $data = DB::table('travellers')
        ->selectRaw('id as idd,full_name,pax_type,first_name,last_name,dob,email,gender,phone,passport_number,passport_expiry_date,nationality,dob')
        ->get();
        return DataTables::of($data)->addIndexColumn()->make(true);
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
        // Validate the request
        $validator = validator($request->all(),
            ['pax_type' => 'required'],
            ['title_val' => 'required'],
            ['first_name' => 'required'],
            ['last_name' => 'required'],
            ['dob' => 'required'],
            ['email' => 'required'],
            ['gender' => 'required'],
            ['phone' => 'required'],
            ['passport_no' => 'required'],
            ['p_expiry_date' => 'required'],
            ['nationality' => 'required'],
        );
        if ($validator->fails()) {
            return $this->ErrorResponse($validator->errors()->all());
        }

        // Get the authenticated user
        $user = auth()->user();

        // Create a new traveler
        $traveler = new Traveller;
        $traveler->pax_type = $request->pax_type;
        $traveler->title = $request->title_val;
        $traveler->first_name = $request->first_name;
        $traveler->last_name = $request->last_name;
        $traveler->full_name = $request->title_val. ' ' . $request->first_name . ' ' . $request->last_name;
        $traveler->dob = $request->dob;
        $traveler->email = $request->email;
        $traveler->gender = $request->gender;
        $traveler->phone = $request->phone;
        $traveler->passport_number = $request->passport_no;
        $traveler->passport_expiry_date = $request->p_expiry_date;
        $traveler->nationality = $request->nationality;
        $traveler->created_by = $user->id;


        if ($request->hasFile('passport_picture')) {

            $request_image = $request->file('passport_picture');
            $image_name = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

            $image_path = public_path('/uploads/travler/passport/');
            if (!File::exists($image_path)) {
                File::makeDirectory($image_path, 0777, true);
            }

            $request_image->move($image_path, $image_name);
            $traveler->passport_path = '/uploads/travler/passport/' . $image_name;

        } else {
            $profilePicturePath = null;
        }
        $traveler->save();

        // Return a success response
        return response()->json(['message' => 'Successfully Traveler Created.', 'types' => 's']);
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
    public function destroy(string $id)
    {
        //
    }
}

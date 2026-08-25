<?php

namespace App\Http\Controllers\Admin\Traveler;

use App\Http\Controllers\Controller;
use App\Models\Traveller\Traveller;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use \Illuminate\Validation\ValidationException;
use Yajra\DataTables\DataTables;

class TravelerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $auth = auth()->user();
        $data = DB::table('travellers')
            ->selectRaw('id as idd,full_name,pax_type,first_name,last_name,dob,email,gender,phone,passport_number,passport_expiry_date,nationality,dob,f_username(travellers.created_by) as created_by,created_at,f_username(travellers.updated_by) as updated_by,updated_at')
            ->where('agent_id', $auth->agent_id)
            ->get();
        return DataTables::of($data)->addIndexColumn()->make(true);
    }

    public function search(Request $request)
    {
        $traveler = Traveller::where('agent_id', Auth::user()->agent_id)
            ->where(function ($query) use ($request) {
                $query->where('first_name', 'like', '%' . $request->parm . '%')
                    ->orWhere('last_name', 'like', '%' . $request->parm . '%')
                    ->orWhere('full_name', 'like', '%' . $request->parm . '%')
                    ->orWhere('passport_number', 'like', '%' . $request->parm . '%')
                    ->orWhere('email', 'like', '%' . $request->parm . '%')
                    ->orWhere('phone', 'like', '%' . $request->parm . '%');
            })
            ->get();

        return response()->json($traveler);
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
            $request->validate([
                'pax_type'         => 'required',
                'title_val'        => 'required|string',
                'first_name'       => 'required|string|max:100',
                'last_name'        => 'required|string|max:100',
                'dob'              => 'required',
                'gender'           => 'required',
                'nationality'      => 'required',
                'passport_no'      => 'required|string|max:50|unique:travellers,passport_number',
                'p_expiry_date'    => 'required',
                'email'            => 'nullable|email|max:100',
                'phone'            => 'nullable|string|max:20',
                'passport_picture' => 'required|file|max:4096', // Max 4MB
            ], [
                'passport_picture.required' => 'Passport image file is required.',
                'passport_picture.max'      => 'Passport image must not exceed 4 MB.',
            ]);

            // Get the authenticated user
            $user = auth()->user();

            // Create a new traveler
            $traveler                       = new Traveller;
            $traveler->pax_type             = $request->pax_type;
            $traveler->title                = $request->title_val;
            $traveler->first_name           = $request->first_name;
            $traveler->last_name            = $request->last_name;
            $traveler->full_name            = $request->title_val . ' ' . $request->first_name . ' ' . $request->last_name;
            $traveler->dob                  = date('Y-m-d', strtotime($request->dob));
            $traveler->email                = $request->email;
            $traveler->gender               = $request->gender;
            $traveler->phone                = $request->phone;
            $traveler->passport_number      = $request->passport_no;
            $traveler->passport_expiry_date = date('Y-m-d', strtotime($request->p_expiry_date));
            $traveler->nationality          = $request->nationality;
            $traveler->agent_id             = $user->agent_id;
            $traveler->created_by           = $user->id;

            if ($request->hasFile('passport_picture')) {

                $request_image = $request->file('passport_picture');
                $image_name    = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

                $image_path = public_path('/uploads/travler/passport/');
                if (! File::exists($image_path)) {
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
        } catch (ValidationException $e) {
            $firstMessage = $e->validator->errors()->first() ?: 'Validation failed.';
            return response()->json([
                'message' => $firstMessage,
                'errors'  => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            report($e); // Log server error
            return response()->json([
                'message' => 'An unexpected server error occurred. Please try again later.'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    public function viewData(Request $request)
    {
        $traveler = Traveller::find($request->id);
        return response()->json($traveler);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    public function fetchTravelerById(Request $request)
    {
        $traveler = Traveller::find($request->id);
        if (! $traveler) {
            return response()->json(['message' => 'Traveler not found.', 'types' => 'e'], 404);
        }
        // Append file size if passport image exists
        if ($traveler->passport_path && File::exists(public_path($traveler->passport_path))) {
            $traveler->passport_file_size = File::size(public_path($traveler->passport_path));
        } else {
            $traveler->passport_file_size = 0;
        }
        return response()->json($traveler);
    }

    /**
     * Update the specified resource in storage.
     */
    // public function update(Request $request)
    // {
    //     // Get the authenticated user
    //     $user = auth()->user();

    //     // Create a new traveler
    //     $traveler                       = Traveller::find($request->pax_id);
    //     $traveler->pax_type             = $request->pax_type;
    //     $traveler->title                = $request->title_val;
    //     $traveler->first_name           = $request->first_name;
    //     $traveler->last_name            = $request->last_name;
    //     $traveler->full_name            = $request->title_val . ' ' . $request->first_name . ' ' . $request->last_name;
    //     $traveler->dob                  = $request->dob ? date('Y-m-d', strtotime($request->dob)) : $traveler->dob;
    //     $traveler->email                = $request->email;
    //     $traveler->gender               = $request->gender;
    //     $traveler->phone                = $request->phone;
    //     $traveler->passport_number      = $request->passport_no;
    //     $traveler->passport_expiry_date = $request->p_expiry_date ? date('Y-m-d', strtotime($request->p_expiry_date)) : $traveler->passport_expiry_date;
    //     $traveler->nationality          = $request->nationality;
    //     $traveler->updated_by           = $user->id;

    //     if ($request->hasFile('passport_picture')) {

    //         $request_image = $request->file('passport_picture');
    //         $image_name    = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

    //         $image_path = public_path('/uploads/travler/passport/');
    //         if (! File::exists($image_path)) {
    //             File::makeDirectory($image_path, 0777, true);
    //         }

    //         if ($traveler->passport_path) {
    //             if ($traveler->passport_path) {
    //                 $filePath = public_path() . $traveler->passport_path;
    //                 File::delete($filePath);
    //             }
    //         }

    //         $request_image->move($image_path, $image_name);
    //         $traveler->passport_path = '/uploads/travler/passport/' . $image_name;
    //     } else {
    //         $profilePicturePath = null;
    //     }
    //     $traveler->save();

    //     // Return a success response
    //     return response()->json(['message' => 'Successfully Traveler Details Updated.', 'types' => 's']);
    // }

    public function update(Request $request)
    {
        try {
            // Validate inputs
            $request->validate([
                'pax_id'        => 'required',
                'pax_type'      => 'required',
                'title_val'     => 'required|string',
                'first_name'    => 'required|string|max:100',
                'last_name'     => 'required|string|max:100',
                'dob'           => 'required',
                'gender'        => 'required',
                'nationality'   => 'required',
                'passport_no' => 'required|string|max:50|unique:travellers,passport_number',
                'p_expiry_date' => 'required',
                'email'         => 'nullable|email|max:100',
                'phone'         => 'nullable|string|max:20',
                'passport_picture' => 'nullable|file|max:4096',
            ], [
                'passport_picture.max' => 'Passport image must not exceed 4 MB.',
            ]);

            // Find the traveler
            $traveler = Traveller::find($request->pax_id);
            if (! $traveler) {
                return response()->json([
                    'message' => 'Traveller not found or may have been deleted.'
                ], 404);
            }

            $user = auth()->user();

            $traveler->pax_type             = $request->pax_type;
            $traveler->title                = $request->title_val;
            $traveler->first_name           = $request->first_name;
            $traveler->last_name            = $request->last_name;
            $traveler->full_name            = $request->title_val . ' ' . $request->first_name . ' ' . $request->last_name;
            $traveler->dob                  = $request->dob ? date('Y-m-d', strtotime($request->dob)) : $traveler->dob;
            $traveler->email                = $request->email;
            $traveler->gender               = $request->gender;
            $traveler->phone                = $request->phone;
            $traveler->passport_number      = $request->passport_no;
            $traveler->passport_expiry_date = $request->p_expiry_date ? date('Y-m-d', strtotime($request->p_expiry_date)) : $traveler->passport_expiry_date;
            $traveler->nationality          = $request->nationality;
            $traveler->updated_by           = $user->id;

            if ($request->hasFile('passport_picture')) {
                $request_image = $request->file('passport_picture');
                $image_name    = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();
                $image_path    = public_path('/uploads/travler/passport/');

                if (! File::exists($image_path)) {
                    File::makeDirectory($image_path, 0777, true);
                }

                if ($traveler->passport_path) {
                    File::delete(public_path($traveler->passport_path));
                }

                $request_image->move($image_path, $image_name);
                $traveler->passport_path = '/uploads/travler/passport/' . $image_name;
            }

            $traveler->save();

            return response()->json([
                'message' => 'Traveller updated successfully.',
                'types'   => 's'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // First field-level error as the toast message
            $firstMessage = $e->validator->errors()->first() ?: 'Validation failed.';
            return response()->json([
                'message' => $firstMessage,
                'errors'  => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'An unexpected server error occurred. Please try again later.'
            ], 500);
        }
    }

    


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        if ($request->id) {

            $traveler = Traveller::where('id', $request->id)->first();
            if ($traveler->passport_path) {
                if ($traveler->passport_path) {

                    $filePath = public_path() . $traveler->passport_path;
                    File::delete($filePath);
                }
            }
            $traveler->delete();
            $success = '';
            return response()->json(['message' => 'Successfully Traveler deleted.', 'types' => 's']);
        } else {
            $error = 'Id can not be null.';
            return response()->json(['message' => 'Id can not be null.', 'types' => 'e'], 400);
        }
    }
}

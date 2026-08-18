<?php

namespace App\Http\Controllers\Admin\Area;

use App\Models\User;
use App\Models\Area\Area;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\BaseController;

class AreaController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = DB::table('areas as ar')
            ->join('districts as d', 'ar.district_id', 'd.id')
            ->join('divisions as div', 'ar.division_id', 'div.id')
            ->selectRaw('ar.id as idd,ar.name,d.name as district,div.name as division,ar.created_at,ar.status,ar.updated_at,f_username(ar.updated_by) updated_by,f_username(ar.created_by) created_by')->get();

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

        // $auth = User::where('email', $request->useEmail)->first();
        $auth = Auth::user();

        $validator = validator($request->all(),
            ['area_name' => 'required'],
            ['division_id' => 'required'],
            ['district_name' => 'required'],
        );
        if ($validator->fails()) {
            return $this->ErrorResponse($validator->errors()->all());
        }

        $area = new Area;
        $area->name = $request->area_name;
        $area->division_id = $request->division_id;
        $area->district_id = $request->district_id;
        $area->status = 1;
        $area->created_by = $auth->id;
        $area->save();

        $success = '';

        return $this->SuccessResponse($success, 'Successfully Zone Saved.');

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
        $area = Area::find($request->id);
        return response()->json($area);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $auth = Auth::user();

        // $validator = validator($request->all(),
        //     ['area_name' => 'required'],
        //     ['division_id' => 'required'],
        //     ['district_name' => 'required'],
        // );
        // if ($validator->fails()) {
        //     return $this->ErrorResponse($validator->errors()->all());
        // }

        $area = Area::where('id', $request->area_id)->first();
        $area->name = $request->area_name != null ? $request->area_name : $area->name;
        $area->division_id = $request->division_id != null ? $request->division_id : $area->division_id;
        $area->district_id = $request->district_id != null ? $request->district_id : $area->district_id;
        $area->status = $request->status_val != null ? $request->status_val : $area->status;
        $area->updated_by = $auth->id;
        $area->save();

        $success = '';
        return $this->SuccessResponse($success, 'Successfully Zone Updated.');

    }

    public function changeAreaStatus(Request $request)
    {
        if ($request->id) {

            $area = Area::where('id', $request->id)->first();
            if ($area->status == 1) {

                $area->status = 2;
            } else if ($area->status == 2) {
                $area->status = 1;
            }
            $area->save();
            $success = '';
            return $this->SuccessResponse($success, 'Successfully Zone status updated.');

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

            $area = Area::where('id', $request->id)->first();
            $area->delete();
            $success = '';
            return $this->SuccessResponse($success, 'Successfully Zone deleted.');

        } else {
            $error = 'Id can not be null.';
            return $this->ErrorResponse($error);

        }

    }

    public function divisionsList()
    {
        $div = DB::table('divisions')->get();
        return response()->json($div);
    }

    public function districts()
    {
        $dist = DB::table('districts')->get();
        return response()->json($dist);
    }

    public function airports(Request $request)
    {
        $airp = DB::table('airports')->get();
        return response()->json($airp);

        // $data = [];
        // if($request->has('q')){
        //     $search = $request->q;
        //     $data = DB::table("airports")
        //     		->select("id","code","City_name","Airport_Name","Country_name","countryshortcode")
        //     		->where('code','LIKE',"%$search%")
        //     		->get();
        // }
        // return response()->json($data);
    }

    public function districtList(Request $request)
    {
        $dist = DB::table('districts')->where('division_id', $request->id)->orderBy('name', 'asc')->get();
        return response()->json($dist);
    }
}

<?php
namespace App\Http\Controllers\Admin\HelpDesk;

use App\Http\Controllers\BaseController;
use App\Models\Helpdesk\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;

class CategoryController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $data = DB::table('categories as cat')
                ->selectRaw('cat.id as idd,cat.name,cat.parent_id,cat.status,cat.updated_at,f_username(cat.updated_by) as updated_by,f_username(cat.created_by) as created_by,cat.created_at,cat.updated_at,(SELECT name FROM categories WHERE id = cat.parent_id) as parent_category_name')->get();


        return DataTables::of($data)->addIndexColumn()->make(true);
    }

    public function categoriesList()
    {
        $categories = Category::where('parent_id', null)->get();

        return response()->json($categories);
    }
    public function subcategoriesList(Request $request)
    {
        $categories = Category::where('parent_id',$request->cate_id)->get();

        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $category             = new Category();
        $category->name       = $request->input('cate_name');
        $category->parent_id  = $request->input('parent_id', null);
        $category->status     = 1;
        $category->created_by = Auth::user()->id;
        $category->save();

        $success = '';

        return $this->SuccessResponse($success, 'Successfully Saved.');

    }

    public function destroy(Request $request)
    {
        $id = $request->input('id');

        $category = Category::find($id);

        if ($category) {
            if(Category::where('parent_id', $id)->exists()){
                return $this->SuccessResponse('d','Cannot delete category with existing subcategories.');
            }
            $category->delete();
            return $this->SuccessResponse('s', 'Successfully Deleted.');
        } else {
            return $this->ErrorResponse('Category not found.', 404);
        }
    }
}

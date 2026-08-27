<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Jobs\Mail\SendAgentUserCreatedMailJob;
use App\Models\Agent\Agent;
use App\Models\Department\Department;
use App\Models\Designation\Designation;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as Pass;
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
            ->selectRaw('u.name,u.email,u.img_path as img,u.phone,u.status,u.is_active,u.is_primary,u.img_path,u.id as idd,u.created_at,u.updated_at,f_department(u.dept_id) as dept,f_designation(u.designation_id) as desg,u.emp_id,f_username(u.updated_by) as updated_by,f_username(u.created_by) as created_by')->get();

        return DataTables::of($data)->addIndexColumn()->make(true);
    }

    public function getAllUsers()
    {
        $user = DB::table('users')->get();
        return response()->json($user);
    }

    public function getHelpdeskRequesters(Request $request)
    {
        $user = auth()->user() ?? $request->user();
        if (!$user) {
            return response()->json([], 401);
        }
        $agencyId = $user->agent_id ?? Agent::where('user_id', $user->id)->value('id');
        // If user belongs to an agency and is a Primary User
        if ($agencyId && $user->is_primary) {
            $agencyUserIds = User::where('agent_id', $agencyId)->pluck('id')->toArray();
            $ownerId = Agent::where('id', $agencyId)->value('user_id');

            if ($ownerId) {
                $agencyUserIds[] = (int) $ownerId;
            }
            $users = DB::table('users')
                ->whereIn('id', array_unique(array_filter($agencyUserIds)))
                ->select('id', 'name', 'email')
                ->get();
        } else {
            // Non-primary user: Return only the logged-in user
            $users = DB::table('users')
                ->where('id', $user->id)
                ->select('id', 'name', 'email')
                ->get();
        }
        return response()->json($users);
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

        $auth      = User::where('email', $request->useEmail)->first();
        $validator = validator(
            $request->all(),
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
        $user                 = new User;
        $user->name           = $request->name;
        $user->phone          = $request->phone;
        $user->email          = $request->email;
        $user->emp_id         = $request->staff_id;
        $user->dept_id        = $request->dept_name;
        $user->designation_id = $request->desg;
        $user->office_loc_id  = $request->off_loct;
        $user->report_to      = $request->report_to;
        $user->user_role      = $request->role_id;

        if ($request->hasFile('profile_picture')) {
            // Base path is config-driven — on live the web root is outside the app dir
            $user->img_path = app(ImageService::class)
                ->uploadProfileImage($request->file('profile_picture'), $user->img_path);
        }

        $user->type       = 2;
        $user->is_active  = 1;
        $user->status     = 1;
        $user->created_by = $auth->id;
        $user->password   = Hash::make('Gblue@sky7');
        // $user->agent_id = $auth->agent_id;
        $user->save();
        return response()->json(['message' => 'Successfully User Saved.', 'types' => 's']);
    }
    public function agntUserstore(Request $request)
    {
        // Trust the token owner, not the client-sent useEmail — otherwise anyone could
        // create a user under another agency by posting a different email.
        $auth = auth()->user() ?: User::where('email', $request->useEmail)->first();

        if (! $auth) {
            return response()->json(['message' => 'Unable to identify the requesting user.', 'types' => 'e'], 401);
        }

        // Normalise before validating so casing/spacing never creates a duplicate account.
        $request->merge(['email' => strtolower(trim((string) $request->email))]);

        $validator = validator($request->all(), [
            'name'      => 'required|string|max:100',
            'phone'     => 'required|string|max:20',
            'email'     => [
                'required',
                'string',
                'max:150',
                'email:rfc,filter',
                // Blocks things Laravel's filter still accepts: no TLD, trailing dot, double dots.
                'regex:/^[A-Za-z0-9]+([._%+\-][A-Za-z0-9]+)*@[A-Za-z0-9]+([.\-][A-Za-z0-9]+)*\.[A-Za-z]{2,}$/',
            ],
            'staff_id'  => 'required|string|max:50',
            'dept_name' => 'required|string|max:100',
            // 'desg' => 'required',
            // 'off_loct' => 'required',
            // 'report_to' => 'required',
            // 'role_id' => 'required',
        ], [
            'email.email' => 'Please enter a valid email address.',
            'email.regex' => 'Please enter a valid email address.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()->toArray(),
                'types'   => 'e',
            ], 422);
        }

        if ($conflict = $this->emailConflictMessage($request->email)) {
            return response()->json([
                'message' => $conflict,
                'errors'  => ['email' => [$conflict]],
                'types'   => 'e',
            ], 422);
        }

        $department = Department::where('name', trim($request->dept_name))->first();
        if (! $department) {
            $department            = new Department;
            $department->name      = trim($request->dept_name);
            $department->status    = 1;
            $department->created_by = $auth->id;
            $department->save();
        }

        $designation = null;
        if ($request->desg_id) {
            $designation = Designation::where('name', trim($request->desg_id))->first();
            if (! $designation) {
                $designation             = new Designation;
                $designation->name       = trim($request->desg_id);
                $designation->status     = 1;
                $designation->created_by = $auth->id;
                $designation->save();
            }
        }

        $user                 = new User;
        $user->name           = $request->name;
        $user->email          = $request->email;
        $user->emp_id         = $request->staff_id;
        $user->phone          = $request->phone;
        $user->dept_id        = $department->id;
        $user->designation_id = $designation ? $designation->id : null;
        // $user->office_loc_id = $request->off_loct;
        // $user->report_to = $request->report_to;
        // $user->user_role = $request->role_id;
        $user->agent_id = $auth->agent_id;

        if ($request->hasFile('profile_picture')) {
            // Base path is config-driven — on live the web root is outside the app dir
            $user->img_path = app(ImageService::class)
                ->uploadProfileImage($request->file('profile_picture'), $user->img_path);
        }

        // Random per-user password instead of a shared hardcoded one; it only reaches the
        // user through the welcome mail below.
        $generatedPassword = Str::password(10);

        $user->type       = 2;
        $user->is_active  = 1; // active
        $user->status     = 1; // active
        $user->created_by = $auth->id;
        $user->password   = Hash::make($generatedPassword);
        // Already expired so the first login forces a password change.
        $user->password_updated_at  = now();
        $user->password_max_expired = 0;
        // $user->agent_id = $auth->agent_id;
        $user->save();

        $this->sendAgentUserCreatedMail(
            user: $user,
            createdBy: $auth,
            department: $department->name,
            designation: $designation?->name,
            password: $generatedPassword,
        );

        return response()->json([
            'message' => 'Successfully User Saved. Login details are being emailed to the user.',
            'types'   => 's',
        ]);
    }

    // Welcome mail must never delay or break user creation: on the sync driver the job is
    // deferred until after the HTTP response is flushed, so the SMTP round trip never
    // blocks the save. Any real queue driver hands it to a worker instead.
    private function sendAgentUserCreatedMail(User $user, User $createdBy, string $department, ?string $designation, string $password): void
    {
        try {
            $agencyName = Agent::where('id', $user->agent_id)->value('name');

            $pending = SendAgentUserCreatedMailJob::dispatch(
                recipientEmail: $user->email,
                userName: $user->name,
                agencyName: (string) ($agencyName ?: 'your agency'),
                username: $user->email,
                phone: (string) $user->phone,
                department: $department,
                designation: (string) ($designation ?? ''),
                defaultPassword: $password,
                portalUrl: rtrim((string) config('app.url'), '/'),
                createdByName: (string) $createdBy->name,
            );

            if (config('queue.default') === 'sync') {
                $pending->afterResponse();
            }
        } catch (\Throwable $e) {
            Log::error('Agent user created mail dispatch failed', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // Returns a human message naming the owning agency when the email is already taken.
    private function emailConflictMessage(string $email): ?string
    {
        $existing = User::where('email', $email)->first();

        if (! $existing) {
            return null;
        }

        $agencyName = $existing->agent_id
            ? Agent::where('id', $existing->agent_id)->value('name')
            : null;

        if ($agencyName) {
            return "This email already exist with {$agencyName}.";
        }

        return 'This email already exist in the system.';
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
        //dd($request->all());
        //$data = User::find($request->id);
        $data = User::where('id', $request->id)->where('agent_id', auth()->user()->agent_id)->first();
        //dd($data);

        if (!$data) {
            return response()->json(['message' => 'User not found.', 'types' => 'e'], 404);
        }

        // Resolve through the service — the file may live outside this app's public dir on live
        $profileImagePath = app(ImageService::class)->resolveProfileImagePath($data->img_path);
        $data->profile_file_size = $profileImagePath ? File::size($profileImagePath) : 0;

        // Edit form expects names (same as create), not raw FK ids
        $data->dept_name = $data->dept_id
            ? Department::where('id', $data->dept_id)->value('name')
            : null;
        $data->desg_name = $data->designation_id
            ? Designation::where('id', $data->designation_id)->value('name')
            : null;

        return response()->json($data);
        //dd($request->all());

        // $user = User::find($request->id);
        // if (! $user) {
        //     return response()->json(['message' => 'User not found.', 'types' => 'e'], 404);
        // }

        // if ($user->img_path && File::exists(public_path($user->img_path))) {
        //     $user->profile_file_size = File::size(public_path($user->img_path));
        // } else {
        //     $user->profile_file_size = 0;
        // }
        // return response()->json([$user]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        // dd($request->all());
        $auth = User::where('email', $request->useEmail)->first();

        $user = User::where('id', $request->user_id)->where('agent_id', auth()->user()->agent_id)->first();
        if (! $user) {
            return response()->json(['message' => 'User not found.', 'types' => 'e'], 404);
        }

        // Mirror create: resolve department/designation by name (create row if missing)
        if ($request->filled('dept_name')) {
            $department = Department::where('name', trim($request->dept_name))->first();
            if (! $department) {
                $department             = new Department;
                $department->name       = trim($request->dept_name);
                $department->status     = 1;
                $department->created_by = $auth->id;
                $department->save();
            }
            $user->dept_id = $department->id;
        }

        if ($request->filled('desg')) {
            $designation = Designation::where('name', trim($request->desg))->first();
            if (! $designation) {
                $designation             = new Designation;
                $designation->name       = trim($request->desg);
                $designation->status     = 1;
                $designation->created_by = $auth->id;
                $designation->save();
            }
            $user->designation_id = $designation->id;
        } elseif ($request->has('desg') && trim((string) $request->desg) === '') {
            $user->designation_id = null;
        }

        $user->name          = $request->name ? $request->name : $user->name;
        $user->phone         = $request->phone ? $request->phone : $user->phone;
        $user->email         = $request->email ? $request->email : $user->email;
        $user->emp_id        = $request->staff_id ? $request->staff_id : $user->emp_id;
        $user->office_loc_id = $request->off_loct ? $request->off_loct : $user->office_loc_id;
        $user->report_to     = $request->report_to ? $request->report_to : $user->report_to;
        $user->user_role     = $request->role_id ? $request->role_id : $user->user_role;

        if ($request->hasFile('profile_picture')) {
            // Base path is config-driven — on live the web root is outside the app dir
            $user->img_path = app(ImageService::class)
                ->uploadProfileImage($request->file('profile_picture'), $user->img_path);
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
        if (! $request->useridStatus) {
            return $this->ErrorResponse('Id can not be null.');
        }

        $auth = auth()->user();
        $user = User::where('id', $request->useridStatus)
            ->where('agent_id', $auth->agent_id)
            ->first();

        if (! $user) {
            return $this->ErrorResponse('User not found.');
        }

        $allowed = [1, 2, 3, 4];
        $status  = (int) $request->status;
        if (! in_array($status, $allowed, true)) {
            return $this->ErrorResponse('Invalid status.', [], 422);
        }

        // Cannot change own login status (lock yourself out)
        if ((int) $user->id === (int) $auth->id) {
            return $this->ErrorResponse('You cannot change your own account status.', [], 422);
        }

        // Primary agency user stays Active — protects owner login
        if ((int) $user->is_primary === 1 && $status !== 1) {
            return $this->ErrorResponse('Primary agency user status cannot be blocked.', [], 422);
        }

        $user->status    = $status;
        // Active → login allowed; Hold / Locked / Deactivated → block
        $user->is_active = $status === 1 ? 1 : 0;
        $user->updated_by = $auth->id;
        $user->save();

        return $this->SuccessResponse(['status' => $status], 'Successfully User status updated.');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed']
        ]);

        $user = $request->user();

        if (! Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
            ], 422);
        }

        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'message' => 'New password must be different from the current password.',
            ], 422);
        }

        $user->password            = Hash::make($request->new_password);
        $user->password_updated_at = now();
        $user->login_attamp        = 0;
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    public function resetUserPassword(Request $request)
    {
        $request->validate([
            'id' => ['required', 'exists:users,id'],
            'password' => ['required', 'confirmed', Pass::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user = User::where('id', $request->id)->where('agent_id', auth()->user()->agent_id)->first();
        if (! $user) {
            return $this->ErrorResponse('User not found.');
        }
        $user->password = Hash::make($request->password);
        $user->password_updated_at = now();
        $user->login_attamp = 0;
        $user->save();

        $success = '';
        return $this->SuccessResponse($success, 'Password reset successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        if ($request->id) {

            $user = User::where('id', $request->id)->where('agent_id', auth()->user()->agent_id)->first();
            if (! $user) {
                $error = 'User not found.';
                return $this->ErrorResponse($error);
            }
            if ($user->img_path) {
                app(ImageService::class)->deleteProfileImageByDbPath($user->img_path);
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

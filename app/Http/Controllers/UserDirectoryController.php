<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserDirectoryController extends Controller
{
    /** 学生选择导师时使用的导师目录 */
    public function advisors(Request $request)
    {
        return response()->json(
            User::where('role', 'advisor')
                ->orderBy('id')
                ->get(['id', 'name', 'title', 'email'])
        );
    }
}

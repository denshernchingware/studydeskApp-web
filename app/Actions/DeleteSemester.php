<?php

namespace App\Actions;

use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteSemester
{
    public function handle(User $user, Semester $semester): bool
    {
        abort_unless((int) $semester->user_id === (int) $user->getKey(), 403);

        return DB::transaction(function () use ($semester): bool {
            $semester->subjects()->delete();

            return (bool) $semester->delete();
        });
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AboutController extends Controller
{
    public function index(Request $request): View
    {
        $joinStatus = null;

        if ($request->user()) {
            $joinStatus = Staff::query()
                ->where('user_id', (int) $request->user()->id)
                ->value('status');
        }

        return view('public.about', [
            'joinStatus' => $joinStatus,
        ]);
    }

    public function joinTeam(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $staff = Staff::query()->firstOrNew(['user_id' => (int) $user->id]);

        if (! $staff->exists) {
            $staff->original_role = (string) ($user->role ?? 'client');
        }

        if ((string) $staff->status !== Staff::STATUS_APPROVED) {
            $staff->status = Staff::STATUS_PENDING;
            $staff->notes = $staff->notes ?: 'Imewasilishwa kupitia page ya about-us.';
            $staff->approved_at = null;
            $staff->approved_by_admin_id = null;
            $staff->save();

            return redirect()
                ->route('about')
                ->with('success', 'Asante! Tunahakiki taarifa zako.');
        }

        return redirect()
            ->route('about')
            ->with('success', 'Tayari upo kwenye team ya Glamo.');
    }
}

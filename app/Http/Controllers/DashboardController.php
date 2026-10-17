<?php

namespace App\Http\Controllers;

use App\Models\PointSet;
use App\Support\CollectionProgress;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Painting progress of the user's collection, overall and by faction.
     */
    public function __invoke(Request $request): View
    {
        $pointSet = PointSet::current();
        $progress = CollectionProgress::forUser($request->user(), $pointSet);

        return view('dashboard', [
            'overall' => $progress->overall(),
            'byFaction' => $progress->byFaction(),
            'pointSet' => $pointSet,
            'armyListCount' => $request->user()->armyLists()->count(),
        ]);
    }
}

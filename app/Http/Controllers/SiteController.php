<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index()
    {
        $sites = Site::orderBy("name")->paginate(20);

        return view("sites.index", compact("sites"));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Site::create($data);

        return back()->with("status", "Site créé avec succès.");
    }

    public function update(Request $request, Site $site)
    {
        $data = $this->validated($request);

        $site->update($data);

        return back()->with("status", "Site mis à jour.");
    }

    public function destroy(Site $site)
    {
        $site->delete();

        return back()->with("status", "Site supprimé.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            "name" => ["required", "string", "max:255"],
            "address" => ["nullable", "string", "max:255"],
            "latitude" => ["required", "numeric", "between:-90,90"],
            "longitude" => ["required", "numeric", "between:-180,180"],
            "radius_m" => ["required", "integer", "min:10", "max:5000"],
        ]);
    }
}

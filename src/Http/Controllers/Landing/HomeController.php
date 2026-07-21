<?php

namespace EQ\LaravelEcommerce\Http\Controllers\Landing;

use EQ\LaravelEcommerce\Http\Controllers\Controller;
use EQ\LaravelEcommerce\Models\Department;
use EQ\LaravelEcommerce\Models\Provider;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class HomeController extends Controller
{
    /**
     * Render the home view
     */
    public function render(Request $request)
    {
        $shop_url = env('VITE_SHOP_URL');

        $providers = Provider::with('files')->get();
        $providers = $providers->map(function (Provider $v, int $k) use ($shop_url) {
            return [
                'href' => "{$shop_url}/providers/{$v->id}",
                'name' => $v->name,
                'slug' => $v->slug,
                'file' => asset($v->files->first()->path),
            ];
        });

        $departments = Department::all();
        $departments = $departments->map(function (Department $v, int $k) use ($shop_url) {
            return [
                'href' => "{$shop_url}/departments/{$v->id}",
                'name' => $v->name,
                'slug' => $v->slug,
            ];
        });

        return redirect(env('VITE_SHOP_URL'));

        // return Inertia::render('landing/HomeView', [
        //     'logo' => asset('storage/logos/recsa_bw_logo.png'),
        //     'providers' => $providers,
        //     'departments' => $departments,
        // ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\Changelog;
use Illuminate\View\View;

/**
 * Was sich von Release zu Release geändert hat - CHANGELOG.md, gerendert. Siehe App\Support\Changelog.
 */
class ChangelogController extends Controller
{
    public function __invoke(): View
    {
        $html = Changelog::html();

        abort_if($html === null, 404);

        return view('changelog', ['html' => $html]);
    }
}

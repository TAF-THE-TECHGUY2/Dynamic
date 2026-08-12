<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Every admin action authorises itself. The React sidebar hides what a role
     * cannot reach, but hiding a link is not authorisation — this is.
     */
    use AuthorizesRequests;
}

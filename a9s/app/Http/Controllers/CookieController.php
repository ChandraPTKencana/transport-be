<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;

class CookieController extends Controller
{
  public function clear(Request $request)
  {
      // Ambil semua cookie dari request
      $cookies = $request->cookies->all();

      $response = new Response(['message' => 'All cookies cleared.']);

      // Loop semua cookie dan set ulang dengan expired time
      foreach ($cookies as $name => $value) {
          $response->withCookie(
              Cookie::forget($name)
          );
      }

      return $response;
  }
  
  
}

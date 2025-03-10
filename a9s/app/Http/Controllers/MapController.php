<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Spatie\Browsershot\Browsershot;

use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;
use Illuminate\Support\Facades\File;

class MapController extends Controller
{

  public function get(Request $request)
  {
    // 3.704609834788131, 98.66065311044704
    $latitude = $request->query('lat', '3.704609834788131'); // Default lat
    $longitude = $request->query('lng', '98.66065311044704'); // Default lng
    $zoom = $request->query('zoom', '19'); // Default zoom

    // Google Maps URL
    $url = "https://www.google.com/maps/@{$latitude},{$longitude},{$zoom}z";

    // Set Chrome executable path (for Windows)
    $chromePath = 'C:\Program Files\Google\Chrome\Application\chrome.exe';

    // Capture screenshot as Base64
    // $base64Image = base64_encode(
    //     Browsershot::url($url)
    //         ->setChromePath($chromePath) // Set Chrome path manually for Windows
    //         ->windowSize(1366, 768) // Set viewport size
    //         ->waitUntilNetworkIdle() // Wait for page to fully load
    //         // ->save(storage_path('app/public/map_screenshot.png'));
    //         // ->fullPage()
    //         ->screenshot()
    // );

    $base64Image="";
    // Browsershot::url($url)
    //         ->setChromePath($chromePath) // Set Chrome path manually for Windows
    //         ->windowSize(1366, 768) // Set viewport size
    //         ->waitUntilNetworkIdle() // Wait for page to fully load
    //         ->save(files_path('map_screenshot.png'));
    try {

        // $svg_file=File::get(files_path("/location_on.svg"));
        $svg_file=File::get(files_path("/location_on.png"));
        //code...
        Image::read(Browsershot::url($url)
        ->setChromePath($chromePath) // Set Chrome path manually for Windows
        ->windowSize(1366, 768) // Set viewport size
        ->waitUntilNetworkIdle() // Wait for page to fully load
        // ->save(storage_path('app/public/map_screenshot.png'));
        // ->fullPage()
        ->screenshot())->crop(737.64, 414.72,314.18,176.64)->place(Image::read($svg_file)->resize(30,30),'center',0,-10)
        ->save(files_path('/map_screenshot.png'));
    } catch (\Exception $e) {
        return response()->json([
            'pesan' => $e->getMessage()
        ]);
    }
    return response()->json([
        'image' => 'data:image/png;base64,' . $base64Image
    ]);
  }

}

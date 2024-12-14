<?php

namespace App\Http\Controllers\Admin;

use ZipArchive;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class AddonController extends Controller
{
    public function index()
    {
        return view('admin.addons.index');
    }

    public function store(Request $request)
    {
        // Validate the incoming file to ensure it's a ZIP file
        $request->validate([
            'file' => 'required|file|mimes:zip',
        ]);

        // Get the uploaded file from the request
        $uploadedFile = $request->file('file');

        // Open the ZIP file using ZipArchive without saving it first
        $zip = new ZipArchive;
        $tempFilePath = $uploadedFile->getRealPath();

        // Check if the ZIP file can be opened
        if ($zip->open($tempFilePath) === TRUE) {
            // Define the path to the Modules folder
            $destinationPath = base_path('Modules');

            // Ensure the Modules folder exists
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            // Extract the ZIP file to the Modules folder
            $zip->extractTo($destinationPath);

            // Close the ZIP file after extraction
            $zip->close();

            // Get module name (assuming the ZIP file name matches the module name)
            $module_name = pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);

            // Specify the path to the module's migrations folder
            $moduleMigrationsPath = base_path('Modules/' . $module_name . '/Database/Migrations');

            // Check if the migrations folder exists and contains migration files
            if (File::exists($moduleMigrationsPath)) {
                // Dynamically add the module's migrations path to the migrator
                $migrator = app('migrator');
                $migrator->path($moduleMigrationsPath);

                // Run the migrations from the module's migration path
                Artisan::call('migrate', ['--force' => true]);
            }

            // Update the modules_statuses.json file
            $filePath = base_path('modules_statuses.json');

            // Read the contents of the JSON file
            $jsonContents = File::get($filePath);

            // Decode the JSON into an associative array
            $data = json_decode($jsonContents, true);

            // Add the new key-value pair to the array
            $data[$module_name] = true;

            // Encode the array back into JSON format
            $newJsonContents = json_encode($data, JSON_PRETTY_PRINT);

            // Write the updated contents back to the file
            File::put($filePath, $newJsonContents);

            return response()->json([
                'message' => 'Files successfully extracted and migrations applied',
                'redirect' => route('admin.addons.index'),
            ]);
        } else {
            return response()->json('Failed to open ZIP file', 406);
        }
    }
}

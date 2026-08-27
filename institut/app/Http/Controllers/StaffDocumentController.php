<?php

namespace App\Http\Controllers;

use App\Models\StaffDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffDocumentController extends Controller
{
    public function download(StaffDocument $document): StreamedResponse
    {
        abort_if($document->trashed(), 404);

        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
        $name = $document->label
            ? trim($document->label) . '.' . $extension
            : basename($document->file_path);

        return Storage::disk('public')->download($document->file_path, $name);
    }
}

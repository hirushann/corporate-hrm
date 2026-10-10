<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    public function __invoke(EmployeeDocument $document): StreamedResponse
    {
        abort_unless(auth()->user()->can('employees.view'), 403);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        activity()->performedOn($document)->causedBy(auth()->user())->log('downloaded');

        return Storage::disk('local')->download($document->path, $document->original_name);
    }
}

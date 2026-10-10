<?php

namespace App\Http\Controllers;

use App\Models\CompanyDocument;
use Illuminate\Support\Facades\Storage;

class CompanyDocumentController extends Controller
{
    public function __invoke(CompanyDocument $document)
    {
        abort_unless(auth()->user()->can('documents.view'), 403);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }
}

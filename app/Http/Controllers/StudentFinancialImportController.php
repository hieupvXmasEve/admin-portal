<?php

namespace App\Http\Controllers;

use App\Services\StudentFinancialImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class StudentFinancialImportController extends Controller
{
    public function __construct(
        private StudentFinancialImportService $importService
    ) {}

    /**
     * Display the student financial import page.
     */
    public function index(): Response
    {
        return Inertia::render('StudentScholarships/Imports/Students', [

        ]);
    }

    /**
     * Preview the import file before processing.
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'preview_rows' => 'nullable|integer|min:5|max:50',
        ]);

        try {
            $result = $this->importService->previewImport(
                $request->file('file'),
                ['preview_rows' => $validated['preview_rows'] ?? 10]
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result['data']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error']
                ], 422);
            }
        } catch (\Exception $e) {
            Log::error('Student financial import preview failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to preview import: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process the import file.
     */
    public function import(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'column_mapping' => 'required|array',
            'column_mapping.*' => 'required|string',
        ]);

        try {
            $result = $this->importService->processImport(
                $request->file('file'),
                $validated['column_mapping']
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result['data'],
                    'message' => 'Import completed successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error']
                ], 422);
            }
        } catch (\Exception $e) {
            Log::error('Student financial import processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process import: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download the import template.
     */
    public function downloadTemplate()
    {
        try {
            $filePath = $this->importService->generateTemplate();

            return response()->download($filePath, basename($filePath))->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Failed to generate student financial import template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors(['error' => 'Failed to generate template: ' . $e->getMessage()]);
        }
    }
}

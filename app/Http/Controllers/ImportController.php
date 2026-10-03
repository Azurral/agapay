<?php

namespace App\Http\Controllers;

use App\Exceptions\ImportFileException;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\ExcelImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Figma 470:540 (Admin) / 430:1887 (Data Encoder) Excel Import: upload, Processing Feedback and the staged preview. */
class ImportController extends Controller
{
    private const PREVIEW_ROWS = 200;

    /** A 20,000-row masterlist takes minutes to read and import, well past PHP's default 30 seconds. */
    private const TIME_LIMIT_SECONDS = 600;

    public function __construct(private readonly ExcelImportService $imports) {}

    public function index(Request $request): View
    {
        $batches = ImportBatch::where('user_id', $request->user()->id);
        $batch = $request->filled('batch')
            ? $batches->whereKey((int) $request->query('batch'))->firstOrFail()
            : $batches->where('status', ImportBatch::STAGED)->latest('id')->first();

        // A long sheet lists the rows that need attention first, so every exclusion reason stays visible.
        $problemsFirst = $batch && array_sum($batch->counts) > self::PREVIEW_ROWS;

        return view('import.index', [
            'batch' => $batch,
            'rows' => $batch?->rows()->reorder()
                ->when($problemsFirst, fn ($query) => $query->orderByRaw(
                    'CASE status WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END',
                    [ImportRow::UNREADABLE, ImportRow::DUPLICATE, ImportRow::FLAGGED, ImportRow::UPDATE],
                ))
                ->orderBy('row_number')->limit(self::PREVIEW_ROWS)->get() ?? collect(),
            'problemsFirst' => $problemsFirst,
            'previewLimit' => self::PREVIEW_ROWS,
            'tooLarge' => $request->boolean('too_large') ? ExcelImportService::tooLargeMessage() : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $file = $request->file('file');
        set_time_limit(self::TIME_LIMIT_SECONDS);

        $error = match (true) {
            ! $file instanceof UploadedFile => 'Choose a spreadsheet to upload.',
            in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) => ExcelImportService::tooLargeMessage(),
            ! $file->isValid() => 'The upload did not finish. Please try again.',
            default => null,
        };
        if ($error !== null) {
            return back()->withErrors(['file' => $error]);
        }

        try {
            $batch = $this->imports->stage($file, $request->user());
        } catch (ImportFileException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('import.index', ['batch' => $batch]);
    }

    public function confirm(Request $request, ImportBatch $batch): RedirectResponse
    {
        abort_unless($batch->user_id === $request->user()->id, 404);
        set_time_limit(self::TIME_LIMIT_SECONDS);

        try {
            $result = $this->imports->confirm($batch, $request->user());
        } catch (ImportFileException $e) {
            return redirect()->route('import.index', ['batch' => $batch])->withErrors(['confirm' => $e->getMessage()]);
        }

        return redirect()->route('import.index')->with('status', sprintf(
            'Imported %d new %s, updated %d, added %d intervention %s.',
            $result['created'], Str::plural('profile', $result['created']), $result['updated'],
            $result['records'], Str::plural('record', $result['records']),
        ));
    }

    public function discard(Request $request, ImportBatch $batch): RedirectResponse
    {
        abort_unless($batch->user_id === $request->user()->id, 404);

        try {
            $this->imports->discard($batch, $request->user());
        } catch (ImportFileException $e) {
            return redirect()->route('import.index', ['batch' => $batch])->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('import.index')->with('status', "Import of {$batch->original_name} discarded.");
    }
}

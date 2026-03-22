<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SqlController extends Controller
{
    private function importPath(): string
    {
        return rtrim(env('SQL_IMPORT_PATH', storage_path('app/sql-import')), '/\\');
    }

    public function meta()
    {
        $path  = $this->importPath();
        $files = glob($path . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        $meta  = [];

        foreach ($files as $f) {
            $name       = basename($f);
            $exportDate = null;
            $gameDate   = null;

            $fp = fopen($f, 'r');
            for ($i = 0; $i < 10; $i++) {
                $line = fgets($fp);
                if ($line === false) break;
                $line = trim($line);

                // # Wednesday, March 18th , 2026 - OOTP Baseball ...
                if (!$exportDate && str_starts_with($line, '#') && preg_match('/(\w+,\s+\w+\s+\d+\w*\s*,?\s*\d{4})/', $line, $m)) {
                    $exportDate = trim($m[1]);
                }

                // # Game date: 1970-05-25
                if (!$gameDate && preg_match('/Game date:\s*(\d{4}-\d{2}-\d{2})/i', $line, $m)) {
                    $gameDate = $m[1];
                }

                if ($exportDate && $gameDate) break;
            }
            fclose($fp);

            $meta[$name] = [
                'export_date' => $exportDate,
                'game_date'   => $gameDate,
                'modified'    => filemtime($f),
            ];
        }

        return response()->json($meta);
    }

    public function index()
    {
        $path = $this->importPath();
        $files = [];

        if (is_dir($path)) {
            $files = collect(glob($path . DIRECTORY_SEPARATOR . '*.sql'))
                ->map(fn($f) => [
                    'name' => basename($f),
                    'size' => filesize($f),
                    'modified' => filemtime($f),
                ])
                ->sortByDesc('modified')
                ->values()
                ->all();
        }

        $lastImport = \App\Models\Setting::instance()->ootp_last_import ?? null;

        return view('adm.sql', compact('files', 'path', 'lastImport'));
    }

    public function process(Request $request)
    {
        $request->validate([
            'file'   => ['required', 'string', 'regex:/^[\w\-. ]+\.sql$/i'],
            'offset' => ['required', 'integer', 'min:0'],
        ]);

        $path     = $this->importPath();
        $fullPath = $path . DIRECTORY_SEPARATOR . $request->input('file');

        if (!file_exists($fullPath)) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        // Ensure the resolved path is still inside the import directory
        if (!str_starts_with(realpath($fullPath), realpath($path))) {
            return response()->json(['error' => 'Invalid file path.'], 403);
        }

        $fileSize   = filesize($fullPath);
        $byteOffset = (int) $request->input('offset');

        $fp = fopen($fullPath, 'r');
        fseek($fp, $byteOffset);

        $lines     = [];
        $lineCount = 0;
        while (!feof($fp) && $lineCount < 1000) {
            $line = fgets($fp);
            if ($line !== false) {
                $lines[] = $line;
                $lineCount++;
            }
        }

        $newOffset = ftell($fp);
        $done      = feof($fp);
        fclose($fp);

        // Execute complete SQL statements
        $buffer = '';
        $errors = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip blank lines and comments
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }

            $buffer .= $line;

            if (str_ends_with(rtrim($trimmed), ';')) {
                try {
                    DB::unprepared($buffer);
                } catch (\Throwable $e) {
                    $errors[] = substr($e->getMessage(), 0, 300);
                }
                $buffer = '';
            }
        }

        $deleted = false;
        if ($done && empty($errors)) {
            if (app()->isProduction()) {
                @unlink($fullPath);
                $deleted = true;
            }
            \App\Models\Setting::instance()->update([
                'ootp_last_import' => now()->format('Y-m-d H:i:s'),
            ]);
        }

        return response()->json([
            'offset'    => $newOffset,
            'file_size' => $fileSize,
            'done'      => $done,
            'errors'    => $errors,
            'deleted'   => $deleted,
        ]);
    }
}

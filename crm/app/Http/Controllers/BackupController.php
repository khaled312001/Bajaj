<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Services\BackupService;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function index()
    {
        return view('backups.index', [
            'backups' => Backup::with('creator:id,name')->latest()->paginate(20),
            'auto' => (bool) Settings::get('backup_auto', 1),
            'retention' => (int) Settings::get('backup_retention_days', 14),
            'last' => Settings::get('last_backup_at'),
            'totalSize' => (int) Backup::where('status', 'ok')->sum('size'),
        ]);
    }

    public function run(Request $request)
    {
        $b = BackupService::run('manual', $request->user()->id);
        Activity::log('backup', null, 'نسخة احتياطية يدوية: ' . $b->filename . ' (' . $b->status . ')');

        return back()->with($b->status === 'ok' ? 'success' : 'warning', $b->status === 'ok' ? 'تم إنشاء النسخة الاحتياطية.' : 'فشل النسخ: ' . $b->error);
    }

    public function settings(Request $request)
    {
        $d = $request->validate(['retention' => ['required', 'integer', 'min:1', 'max:365']]);
        Settings::set('backup_auto', $request->boolean('auto') ? 1 : 0);
        Settings::set('backup_retention_days', $d['retention']);
        Activity::log('settings', null, 'تعديل إعدادات النسخ الاحتياطي');

        return back()->with('success', 'تم الحفظ.');
    }

    public function download(Request $request, Backup $backup)
    {
        abort_unless($backup->status === 'ok' && is_file($backup->path()), 404);
        Activity::log('export', null, 'تنزيل نسخة احتياطية ' . $backup->filename);

        return response()->download($backup->path(), $backup->filename);
    }

    public function restore(Request $request, Backup $backup)
    {
        abort_unless($backup->status === 'ok' && is_file($backup->path()), 404);
        $request->validate(['confirm' => ['required', 'in:استرجاع']]);

        try {
            BackupService::restore($backup, $request->user()->id);
        } catch (\Throwable $e) {
            return back()->with('warning', 'فشل الاسترجاع: ' . $e->getMessage());
        }

        return back()->with('success', 'تم استرجاع البيانات من نسخة ' . $backup->created_at->format('Y/m/d H:i') . '. تم أخذ نسخة احتياطية من الحالة السابقة قبل الاسترجاع.');
    }

    public function destroy(Backup $backup)
    {
        @unlink($backup->path());
        $backup->delete();
        Activity::log('backup', null, 'حذف نسخة احتياطية ' . $backup->filename);

        return back()->with('success', 'تم الحذف.');
    }
}

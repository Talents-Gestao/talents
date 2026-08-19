<?php

namespace App\Http\Controllers\Admin\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskAttachment;
use App\Models\TaskCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskBoardAttachmentController extends Controller
{
    public function store(Request $request, TaskCard $card): RedirectResponse
    {
        $maxKb = (int) config('tasks.max_attachment_kb', 10240);
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.max(1, $maxKb),
                'mimetypes:application/pdf,'
                    .'application/msword,'
                    .'application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
                    .'application/vnd.ms-excel,'
                    .'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
                    .'application/vnd.ms-powerpoint,'
                    .'application/vnd.openxmlformats-officedocument.presentationml.presentation,'
                    .'text/plain,'
                    .'text/csv,'
                    .'image/jpeg,image/png,image/gif,image/webp,image/svg+xml,'
                    .'audio/mpeg,audio/ogg,audio/wav,'
                    .'video/mp4,video/webm,'
                    .'application/zip,application/x-zip-compressed',
            ],
        ]);

        $file = $request->file('file');
        $path = $file->store('task-attachments/'.$card->id, 'public');

        TaskAttachment::query()->create([
            'task_card_id' => $card->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Anexo enviado.');
    }

    public function destroy(TaskAttachment $attachment): RedirectResponse
    {
        if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $attachment->delete();

        return back()->with('success', 'Anexo removido.');
    }
}

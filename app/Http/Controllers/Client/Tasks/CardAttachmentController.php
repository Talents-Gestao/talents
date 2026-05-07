<?php

namespace App\Http\Controllers\Client\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskAttachment;
use App\Models\TaskCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CardAttachmentController extends Controller
{
    public function store(Request $request, TaskCard $card): RedirectResponse
    {
        $this->authorize('attach', $card);

        $maxKb = (int) config('tasks.max_attachment_kb', 10240);
        $request->validate([
            'file' => ['required', 'file', 'max:'.max(1, $maxKb)],
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
        $attachment->loadMissing('card.list.board');
        $card = $attachment->card;
        $this->authorize('attach', $card);

        if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $attachment->delete();

        return back()->with('success', 'Anexo removido.');
    }
}

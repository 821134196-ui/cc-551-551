<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentService
{
    /** 附件保存在本地 storage/app/attachments 目录，按对象类型分文件夹。 */
    public static function store(UploadedFile $file, Model $attachable, User $uploader): Attachment
    {
        $folder = Str::of(class_basename($attachable))
            ->snake()
            ->plural()
            ->append('/' . $attachable->getKey());

        $path = $file->store($folder->toString(), 'attachments');

        return Attachment::create([
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'uploaded_by' => $uploader->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public static function download(Attachment $attachment)
    {
        $disk = Storage::disk('attachments');

        if (! $disk->exists($attachment->path)) {
            abort(404, '附件文件不存在。');
        }

        return response()->streamDownload(function () use ($disk, $attachment) {
            $stream = $disk->readStream($attachment->path);
            fpassthru($stream);
            fclose($stream);
        }, $attachment->original_name, [
            'Content-Type' => $attachment->mime ?: 'application/octet-stream',
        ]);
    }
}

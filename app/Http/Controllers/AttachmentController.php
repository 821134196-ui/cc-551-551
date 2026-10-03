<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\ManuscriptService;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController extends Controller
{
    /**
     * 附件下载：策略确保仅课题学生与其导师可访问，其他导师 403。
     * 文件物理保存在本地 storage/app/attachments 目录。
     */
    public function download(Attachment $attachment): Response
    {
        $this->authorize('download', $attachment);

        $absolute = ManuscriptService::attachmentAbsolutePath($attachment->path);

        abort_unless(is_file($absolute), 404, '附件文件不存在（可能已被清理）。');

        return response()->download($absolute, $attachment->original_name, [
            'Content-Type' => $attachment->mime ?: 'application/octet-stream',
        ]);
    }
}

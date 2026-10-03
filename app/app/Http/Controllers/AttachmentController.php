<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\GuidanceSession;
use App\Models\Submission;
use App\Services\AttachmentService;

class AttachmentController extends Controller
{
    use ChecksOwnership;

    public function download(Attachment $attachment)
    {
        $attachable = $attachment->attachable;

        if ($attachable instanceof Submission) {
            $this->assertCanAccessSubmission($attachable);
        } elseif ($attachable instanceof GuidanceSession) {
            $this->assertCanAccessSession($attachable);
        } else {
            abort(404);
        }

        return AttachmentService::download($attachment);
    }
}

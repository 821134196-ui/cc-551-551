<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Comment;
use App\Models\GuidanceRecord;
use App\Models\ManuscriptVersion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ManuscriptService
{
    public function __construct(private NotificationService $notifications)
    {
    }

    /**
     * 学生提交新稿件版本。
     * - 版本号在课题内自增，旧版本永远保留；
     * - 可携带对导师评语的逐条回应（回应绑定到新版本，便于追溯“在哪一版改的”）；
     * - 被回应的评语进入 resolved（待导师确认），学生不能直接关闭。
     *
     * @param  array<int, array{comment_id:int,content:string}>  $responses
     * @param  array<int, UploadedFile>  $files
     */
    public function submitVersion(
        Project $project,
        User $student,
        string $title,
        ?string $note,
        array $files,
        array $responses = [],
        ?Booking $booking = null,
        bool $isFinal = false,
    ): ManuscriptVersion {
        return DB::transaction(function () use ($project, $student, $title, $note, $files, $responses, $booking, $isFinal) {
            // 版本号在课题内自增；旧版本（含已撤回）永远保留，取号基于全部历史版本
            $versionNo = (int) DB::table('manuscript_versions')->where('project_id', $project->id)->max('version_no') + 1;

            if ($isFinal) {
                // 同一课题同时只有一个最终稿标记
                DB::table('manuscript_versions')->where('project_id', $project->id)->update(['is_final' => false]);
            }

            $version = ManuscriptVersion::create([
                'project_id' => $project->id,
                'student_id' => $student->id,
                'booking_id' => $booking?->id,
                'version_no' => $versionNo,
                'title' => $title,
                'note' => $note,
                'status' => 'active',
                'is_final' => $isFinal,
            ]);

            $this->storeAttachments($version, $files, $student);

            foreach ($responses as $response) {
                $comment = Comment::query()
                    ->whereHas('guidanceRecord', fn ($q) => $q->where('project_id', $project->id))
                    ->find($response['comment_id']);

                if (!$comment) {
                    throw ValidationException::withMessages([
                        'responses' => "评语 #{$response['comment_id']} 不属于本课题，无法回应。",
                    ])->status(422);
                }

                if ($comment->status === Comment::STATUS_CLOSED) {
                    throw ValidationException::withMessages([
                        'responses' => "评语 #{$comment->id} 已由导师关闭，不能再回应。",
                    ])->status(422);
                }

                $version->responses()->create([
                    'comment_id' => $comment->id,
                    'student_id' => $student->id,
                    'content' => $response['content'],
                ]);

                $comment->update(['status' => Comment::STATUS_RESOLVED]);
            }

            if ($project->advisor) {
                $this->notifications->send(
                    $project->advisor,
                    'manuscript_submitted',
                    "收到新稿件（v{$versionNo}）",
                    sprintf('%s 提交了《%s》第 %d 版', $student->name, $title, $versionNo),
                    ['project_id' => $project->id, 'version_id' => $version->id],
                );
            }

            return $version->load(['attachments', 'responses']);
        });
    }

    /**
     * 学生撤回误传稿件。
     * 仅当没有任何指导记录引用该版本时允许；一旦已被引用，记录不可改动、稿件不可撤回。
     */
    public function withdrawVersion(ManuscriptVersion $mVersion, string $reason): ManuscriptVersion
    {
        if (!$mVersion->canWithdraw()) {
            throw ValidationException::withMessages([
                'version' => '该版本已被指导记录引用，不能撤回（历史指导证据必须保留）。',
            ])->status(422);
        }

        $mVersion->update([
            'status' => 'withdrawn',
            'withdrawn_at' => now(),
            'withdraw_reason' => $reason,
        ]);

        return $mVersion;
    }

    /**
     * 导师在约谈后针对某一版本创建指导记录（含多条评语与附件）。
     * 记录一经创建即不可修改、不可删除 —— 路由层不提供编辑入口。
     *
     * @param  array<int, string>  $commentTexts
     * @param  array<int, UploadedFile>  $files
     */
    public function createGuidance(
        Project $project,
        User $advisor,
        ManuscriptVersion $version,
        string $summary,
        array $commentTexts,
        array $files = [],
        ?Booking $booking = null,
    ): GuidanceRecord {
        return DB::transaction(function () use ($project, $advisor, $version, $summary, $commentTexts, $files, $booking) {
            if ($version->project_id !== $project->id) {
                throw ValidationException::withMessages([
                    'manuscript_version_id' => '指导记录必须针对本课题下的稿件版本。',
                ])->status(422);
            }

            $record = GuidanceRecord::create([
                'project_id' => $project->id,
                'booking_id' => $booking?->id,
                'advisor_id' => $advisor->id,
                'manuscript_version_id' => $version->id,
                'summary' => $summary,
            ]);

            foreach ($commentTexts as $text) {
                $record->comments()->create([
                    'advisor_id' => $advisor->id,
                    'content' => $text,
                    'status' => Comment::STATUS_OPEN,
                ]);
            }

            $this->storeAttachments($record, $files, $advisor);

            if ($booking && $booking->status === 'booked') {
                $booking->update(['status' => 'completed']);
                $booking->slot->update(['status' => 'done']);
            }

            $this->notifications->send(
                $project->student,
                'guidance_created',
                '导师留下了新的指导意见',
                sprintf('针对您的第 %d 版稿件《%s》，导师提出了 %d 条评语。', $version->version_no, $version->title, count($commentTexts)),
                ['project_id' => $project->id, 'guidance_record_id' => $record->id],
            );

            return $record->load(['comments', 'attachments', 'manuscriptVersion', 'booking']);
        });
    }

    /** 导师关闭评语（学生无权关闭）。可附确认说明。 */
    public function closeComment(Comment $comment, User $advisor, ?string $note = null): Comment
    {
        $record = $comment->guidanceRecord;
        if ($record->advisor_id !== $advisor->id) {
            throw ValidationException::withMessages(['comment' => '只能关闭自己提出的评语。'])->status(403);
        }

        $comment->update([
            'status' => Comment::STATUS_CLOSED,
            'closed_by' => $advisor->id,
            'closed_at' => now(),
        ]);

        $project = $record->project;
        $this->notifications->send(
            $project->student,
            'comment_closed',
            '一条评语已被导师确认关闭',
            $note ?: sprintf('您对第 %d 版相关意见的修改已获导师确认。', $record->manuscriptVersion->version_no),
            ['comment_id' => $comment->id, 'project_id' => $project->id],
        );

        return $comment;
    }

    /** 导师对学生回应不满意，可把评语重新置为待处理 */
    public function reopenComment(Comment $comment, User $advisor): Comment
    {
        $record = $comment->guidanceRecord;
        if ($record->advisor_id !== $advisor->id) {
            throw ValidationException::withMessages(['comment' => '只能操作自己提出的评语。'])->status(403);
        }

        $comment->update(['status' => Comment::STATUS_OPEN, 'closed_by' => null, 'closed_at' => null]);

        $this->notifications->send(
            $record->project->student,
            'comment_reopened',
            '导师要求继续修改一条意见',
            $comment->content,
            ['comment_id' => $comment->id, 'project_id' => $record->project_id],
        );

        return $comment;
    }

    /**
     * 导师依据“最终稿 + 未解决意见”确认答辩资格。
     * 存在未关闭意见时给出明确提示（导师可在确认知悉的情况下仍做决定，但必须显式 force）。
     */
    public function decideEligibility(Project $project, User $advisor, bool $eligible, ?string $note, bool $force = false): Project
    {
        if ($project->advisor_id !== $advisor->id) {
            throw ValidationException::withMessages(['project' => '只有该课题的指导导师可以确认答辩资格。'])->status(403);
        }

        $finalVersion = $project->manuscriptVersions()->where('is_final', true)->where('status', 'active')->first();
        if ($eligible && !$finalVersion) {
            throw ValidationException::withMessages([
                'defense_eligible' => '尚无学生提交的最终稿，不能确认具备答辩资格。',
            ])->status(422);
        }

        $openCount = $this->openCommentCount($project);
        if ($eligible && $openCount > 0 && !$force) {
            throw ValidationException::withMessages([
                'defense_eligible' => "仍有 {$openCount} 条未关闭的指导意见；如确认资格请显式确认知悉（force=true）。",
                'open_comment_count' => $openCount,
            ])->status(422);
        }

        $project->update([
            'defense_eligible' => $eligible,
            'eligibility_note' => $note,
            'eligibility_decided_at' => now(),
        ]);

        $this->notifications->send(
            $project->student,
            'eligibility_decided',
            $eligible ? '导师确认您具备答辩资格' : '导师暂未确认答辩资格',
            $note ?: ($eligible
                ? '最终稿与修改意见已审核通过。'
                : '请根据最终稿与未解决的问题继续修改。'),
            ['project_id' => $project->id, 'defense_eligible' => $eligible],
        );

        return $project;
    }

    public function openCommentCount(Project $project): int
    {
        return Comment::query()
            ->whereHas('guidanceRecord', fn ($q) => $q->where('project_id', $project->id))
            ->where('status', '!=', Comment::STATUS_CLOSED)
            ->count();
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function storeAttachments(object $attachable, array $files, User $uploader): void
    {
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $folder = $attachable instanceof ManuscriptVersion
                ? 'manuscripts'
                : 'guidance';

            $path = $file->store("attachments/{$folder}/{$attachable->id}", 'local');

            $attachable->attachments()->create([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $uploader->id,
            ]);
        }
    }

    /** 附件本地磁盘读取（下载授权后使用） */
    public static function attachmentAbsolutePath(string $path): string
    {
        return Storage::disk('local')->path($path);
    }
}

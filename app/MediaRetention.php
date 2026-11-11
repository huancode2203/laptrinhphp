<?php

namespace App;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaRetention
{
    public function referenced(int $id): bool
    {
        return DB::table('community_posts')->whereJsonContains('images', $id)->exists()
            || DB::table('reviews')->whereJsonContains('images', $id)->exists()
            || DB::table('chat_messages')->whereJsonContains('attachments', $id)->exists()
            || DB::table('bank_transfer_claims')->whereJsonContains('media_ids', $id)->exists()
            || DB::table('users')->where('avatar_path', '/api/v1/media/'.$id)->exists();
    }

    public function archiveUnused(): int
    {
        $count = 0;
        $ids = DB::table('media_uploads')->where('created_at', '<', now()->subDays(14))->whereNotIn('id', DB::table('media_archives')->whereNull('restored_at')->select('media_upload_id'))->limit(200)->pluck('id');
        foreach ($ids as $id) {
            $count += DB::transaction(function () use ($id): int {
                $row = DB::table('media_uploads')->where('id', $id)->lockForUpdate()->first();
                if (! $row || $this->referenced($id) || ! Storage::disk('local')->exists($row->path)) {
                    return 0;
                }
                $path = 'quarantine/'.Str::uuid();
                abort_unless(Storage::disk('local')->move($row->path, $path), 500);
                DB::table('media_archives')->updateOrInsert(['media_upload_id' => $id], ['original_path' => $row->path, 'archive_path' => $path, 'archived_at' => now(), 'purge_after' => now()->addDays(30), 'restored_at' => null, 'purged_at' => null]);

                return 1;
            });
        }

        return $count;
    }

    public function restore(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $row = DB::table('media_archives')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row && ! $row->purged_at, 404, 'Tệp đã được dọn hoặc không tồn tại.');
            if ($row->restored_at) {
                return;
            }
            abort_unless(Storage::disk('local')->exists($row->archive_path) && ! Storage::disk('local')->exists($row->original_path), 409, 'Tệp chưa sẵn sàng khôi phục.');
            abort_unless(Storage::disk('local')->move($row->archive_path, $row->original_path), 500);
            DB::table('media_archives')->where('id', $id)->update(['restored_at' => now()]);
        });
    }

    public function purge(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $row = DB::table('media_archives')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row && ! $row->restored_at && now()->gte($row->purge_after), 422, 'Chỉ dọn vĩnh viễn tệp hết 30 ngày lưu tạm.');
            abort_if($this->referenced($row->media_upload_id), 409, 'Tệp đang được sử dụng, không thể dọn.');
            if (! $row->purged_at) {
                abort_unless(Storage::disk('local')->delete($row->archive_path), 500);
                DB::table('media_archives')->where('id', $id)->update(['purged_at' => now()]);
            }
        });
    }
}

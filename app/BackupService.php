<?php

namespace App;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupService
{
    private const EXCLUDED = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'auth_codes', 'password_reset_tokens', 'backup_records'];

    public function create(?User $actor, string $purpose = 'manual'): object
    {
        return Cache::lock('hub:database-backup', 120)->block(3, function () use ($actor, $purpose): object {
            $snapshot = DB::transaction(function (): array {
                $tables = $this->tables();
                $rows = [];
                $columns = [];
                foreach ($tables as $table) {
                    abort_if(DB::table($table)->count() > 200000, 422, 'Bảng dữ liệu quá lớn cho sao lưu trong ứng dụng. Hãy dùng sao lưu máy chủ.');
                    $rows[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
                    $columns[$table] = Schema::getColumnListing($table);
                }

                return ['version' => 1, 'created_at' => now()->toIso8601String(), 'database' => config('database.default'), 'columns' => $columns, 'tables' => $rows];
            });
            $reference = (string) Str::uuid();
            $path = 'backups/'.$reference.'.hubbackup';
            $bytes = Crypt::encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
            abort_unless(Storage::disk('local')->put($path, $bytes), 500, 'Chưa ghi được bản sao lưu.');
            $id = DB::table('backup_records')->insertGetId(['reference' => $reference, 'path' => $path, 'created_by' => $actor?->id, 'purpose' => $purpose, 'bytes' => strlen($bytes), 'summary' => json_encode(array_map('count', $snapshot['tables'])), 'created_at' => now(), 'updated_at' => now()]);
            if ($actor) {
                app(BookingService::class)->audit($actor, 'backup.created', $id, ['reference' => $reference, 'purpose' => $purpose], 'backup');
            }

            return DB::table('backup_records')->find($id);
        });
    }

    private function tables(): array
    {
        $tables = array_values(array_diff(Schema::getTableListing(Schema::getCurrentSchemaListing(), schemaQualified: false), self::EXCLUDED));
        sort($tables);

        return $tables;
    }

    public function inspect(string $reference): array
    {
        $record = DB::table('backup_records')->where('reference', $reference)->first();
        abort_unless($record && Storage::disk('local')->exists($record->path), 404, 'Không tìm thấy bản sao lưu.');
        try {
            $snapshot = json_decode(Crypt::decryptString(Storage::disk('local')->get($record->path)), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            abort(422, 'Bản sao lưu hỏng hoặc không được tạo bằng khóa của máy chủ này.');
        }
        abort_unless(is_array($snapshot) && ($snapshot['version'] ?? null) === 1 && is_array($snapshot['tables'] ?? null), 422, 'Định dạng sao lưu không hợp lệ.');
        $tables = array_keys($snapshot['tables']);
        sort($tables);
        $expectedTables = $this->tables();
        abort_unless($tables === $expectedTables, 422, 'Cấu trúc sao lưu không khớp phiên bản website hiện tại. Thiếu: '.implode(', ', array_diff($expectedTables, $tables)).'; dư: '.implode(', ', array_diff($tables, $expectedTables)).'.');
        foreach ($snapshot['tables'] as $table => $rows) {
            abort_unless(($snapshot['columns'][$table] ?? []) === Schema::getColumnListing($table) && is_array($rows), 422, 'Cấu trúc bảng '.$table.' không khớp.');
            foreach ($rows as $row) {
                abort_unless(is_array($row) && array_keys($row) === $snapshot['columns'][$table], 422, 'Dòng dữ liệu sao lưu không hợp lệ.');
            }
        }

        return $snapshot;
    }

    public function restore(User $actor, string $reference): array
    {
        abort_unless($actor->role === 'admin', 403);
        abort_unless(app()->isDownForMaintenance(), 409, 'Bật chế độ bảo trì trên máy chủ trước khi khôi phục CSDL để ngăn giao dịch phát sinh.');

        return Cache::lock('hub:database-restore', 180)->block(3, function () use ($actor, $reference): array {
            $snapshot = $this->inspect($reference);
            $before = $this->create($actor, 'before_restore');
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            abort_unless(in_array($driver, ['sqlite', 'mysql'], true), 422);
            if ($driver === 'mysql') {
                $tables = $connection->select('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
                foreach ($tables as $table) {
                    abort_unless(strcasecmp($table->ENGINE ?? '', 'InnoDB') === 0, 422, 'Khôi phục an toàn yêu cầu tất cả bảng sử dụng InnoDB.');
                }
            }
            Schema::disableForeignKeyConstraints();
            try {
                DB::transaction(function () use ($snapshot, $driver): void {
                    DB::table('backup_records')->update(['created_by' => null]);
                    foreach (array_keys($snapshot['tables']) as $table) {
                        DB::table($table)->delete();
                    }
                    foreach ($snapshot['tables'] as $table => $rows) {
                        foreach (array_chunk($rows, 25) as $chunk) {
                            DB::table($table)->insert($chunk);
                        }
                    }
                    if ($driver === 'sqlite') {
                        abort_if(DB::select('PRAGMA foreign_key_check') !== [], 422, 'Sao lưu có dữ liệu tham chiếu không hợp lệ.');
                    } else {
                        foreach (array_keys($snapshot['tables']) as $table) {
                            foreach (Schema::getForeignKeys($table) as $key) {
                                $query = DB::table($table.' as child')->leftJoin($key['foreign_table'].' as parent', function ($join) use ($key): void {
                                    foreach ($key['columns'] as $i => $column) {
                                        $join->on('child.'.$column, '=', 'parent.'.$key['foreign_columns'][$i]);
                                    }
                                });
                                foreach ($key['columns'] as $column) {
                                    $query->whereNotNull('child.'.$column);
                                }
                                abort_if($query->whereNull('parent.'.$key['foreign_columns'][0])->exists(), 422, 'Sao lưu có tham chiếu bị thiếu trong '.$table.'.');
                            }
                        }
                    }
                    DB::table('sessions')->delete();
                    DB::table('auth_codes')->delete();
                    DB::table('password_reset_tokens')->delete();
                    DB::table('users')->update(['remember_token' => null]);
                });
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            return ['restored' => $reference, 'safety_backup' => $before->reference];
        });
    }
}

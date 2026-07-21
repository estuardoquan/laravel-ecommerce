<?php

namespace App\Models;

use Error;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

class File extends Model
{
    use HasFactory,
        HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'client_original_name',
        'path',
    ];

    public static function storage_put(mixed $file, ?string $name): string
    {
        $path = '';

        try {
            $path = Storage::disk($name)->put("files", $file);
        } catch (Throwable $e) {
            throw new Error($e);
        }

        return $path;
    }

    public static function storage_delete(string $path, ?string $name): bool
    {
        $b = false;

        try {
            $b = Storage::disk($name)->delete($path);
        } catch (Throwable $e) {
            throw new Error($e);
        }

        return $b;
    }

    // public static function storage_url(string $path, ?int $t): string
    // {
    //     return Storage::disk('s3')->temporaryUrl($path, Carbon::now()->addMinutes($t ?: 1));
    // }
}

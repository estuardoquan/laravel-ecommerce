<?php

namespace App\Traits;

use App\Models\File;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasFiles
{
    public function files(): BelongsToMany
    {
        return $this->morphToMany(
            File::class,
            'model',
            'model_has_files',
            'model_id',
            'file_id',
        );
    }
}

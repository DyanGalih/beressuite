<?php

namespace App\Http\Controllers\V1\Admin\Backup;

class DownloadBackupController extends ApiController
{
    public function __invoke(): never
    {
        abort(403);
    }
}

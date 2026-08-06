<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['workspace_id', 'user_id', 'role'])]
class WorkspaceUser extends Pivot
{
    protected $table = 'workspace_user';
}

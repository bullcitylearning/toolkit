<?php

namespace Bcl\Toolkit\Tests\Fixtures\Gated;

use Bcl\Toolkit\Auth\Concerns\HasApiTokens;
use Illuminate\Foundation\Auth\User as AuthUser;

class User extends AuthUser
{
    use HasApiTokens;

    protected $table = 'users';

    protected $guarded = [];
}

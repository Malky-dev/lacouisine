<?php

declare(strict_types=1);

namespace App\Enum;

enum RecipeVisibility: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';
}

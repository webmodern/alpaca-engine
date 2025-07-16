<?php

namespace App\Enums;

enum Status
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived'; // Часто используется для скрытия, но без удаления
}

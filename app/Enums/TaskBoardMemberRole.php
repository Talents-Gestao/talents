<?php

namespace App\Enums;

enum TaskBoardMemberRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Commenter = 'commenter';
    case Viewer = 'viewer';
}

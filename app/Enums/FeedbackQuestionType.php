<?php

declare(strict_types=1);

namespace App\Enums;

enum FeedbackQuestionType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case SingleChoice = 'single_choice';
    case BulletList = 'bullet_list';
    case CcmBlock = 'ccm_block';
    case ActionTable = 'action_table';
    case RatingGroup = 'rating_group';

}

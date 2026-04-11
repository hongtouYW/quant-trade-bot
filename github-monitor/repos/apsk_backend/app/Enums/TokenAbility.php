<?php

namespace App\Enums;

enum TokenAbility: string
{
    case API_ACCESS = 'api:access';
    case TOKEN_REFRESH = 'token:refresh';
}
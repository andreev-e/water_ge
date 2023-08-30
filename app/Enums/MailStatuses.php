<?php

namespace App\Enums;

enum MailStatuses: int
{
    case new = 1;
    case sent = 2;
}

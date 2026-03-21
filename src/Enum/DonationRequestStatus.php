<?php

namespace App\Enum;

enum DonationRequestStatus: string
{
    case PENDING = 'PENDING';
    case ACCEPTED = 'ACCEPTED';
    case REFUSED = 'REFUSED';
    case CANCELLED = 'CANCELLED';
}
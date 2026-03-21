<?php

namespace App\Enum;

enum ListingStatus: string
{
    case DISPONIBLE = 'DISPONIBLE';
    case RESERVEE = 'RESERVEE';
    case DONNEE = 'DONNEE';
}
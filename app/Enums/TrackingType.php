<?php

namespace App\Enums;

enum TrackingType: string
{
    case Expenses = 'expenses';
    case Income = 'income';
    case Both = 'both';
}

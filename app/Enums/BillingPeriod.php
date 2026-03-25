<?php

namespace App\Enums;

/**
 * How the listing price is billed (English snake_case values for API / DB).
 */
enum BillingPeriod: string
{
    case PerDay = 'per_day';
    case PerWeek = 'per_week';
    case PerMonth = 'per_month';
    case PerYear = 'per_year';
}

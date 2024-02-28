<?php

namespace App\Enums;

enum OrderStates: int
{
    case INCOMPLETE = 0;
    case PENDING = 1;
    case REFUNDED = 4;
    case CANCELLED = 5;
    case DECLINED = 6;
    case AWAITING_PAYMENT = 7;
    case COMPLETED = 10;
    case AWAITING_FULFILLMENT = 11;
    case MANUAL_VERIFICATION_REQUIRED = 12;
    case DISPUTED = 13;
}

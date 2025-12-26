<?php

namespace App\Enums;

enum PaymentMethodEnum: string
{
    case CARD = 'card';
    case BANK = 'bank';
    case CASH = 'cash';
    case FROM_APP = 'from_app';
    case MULTICAIXA = 'multicaixa';
}

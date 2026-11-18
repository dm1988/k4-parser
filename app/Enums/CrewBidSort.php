<?php

namespace App\Enums;

enum CrewBidSort: string
{
    case BestMatch = 'best';
    case Credit = 'credit';
    case Block = 'block';
    case DaysOff = 'days_off';
    case Pay = 'pay';
    case LineId = 'id';
    case MyBidList = 'my_bid_list';
    case Submitted = 'submitted';

    public function label(): string
    {
        return match ($this) {
            self::BestMatch => 'Best match',
            self::Credit => 'Highest credit',
            self::Block => 'Highest block time',
            self::DaysOff => 'Most days off',
            self::Pay => 'Highest estimated line value',
            self::LineId => 'Line ID',
            self::MyBidList => 'My Bid List',
            self::Submitted => 'Submitted bid order',
        };
    }
}

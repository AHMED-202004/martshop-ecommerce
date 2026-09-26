<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Sale = 'sale';
    case SettlementRelease = 'settlement_release';
    case DisputeHold = 'dispute_hold';
    case DisputeRelease = 'dispute_release';
    case Refund = 'refund';
    case WithdrawalHold = 'withdrawal_hold';
    case WithdrawalRelease = 'withdrawal_release';
    case Withdrawal = 'withdrawal';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';
}

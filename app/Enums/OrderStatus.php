<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Created = 'created';
    case PendingPayment = 'pending_payment';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Canceled = 'canceled';
    case Refunded = 'refunded';
}

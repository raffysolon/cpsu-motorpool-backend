<?php

namespace App\Enums;

enum TrackerCallerType: string
{
    case IndividualTracker = 'individual_tracker';
    case TrustedGateway = 'trusted_gateway';
    case TemporaryLegacy = 'temporary_legacy';
}

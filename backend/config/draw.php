<?php

// Central knobs for the two buying models. Change here, not scattered across code.
return [
    'batch_size' => 100,          // entries per club pool (odds 1 in 100)
    'entry_pct' => 1,             // % of listed price charged as the advance booking
    'wallet_cap_pct' => 1,        // max % of an item's price payable from wallet on a 100% buy
    // Seats one customer may hold in a single pool. Each seat must still be a
    // DIFFERENT product in that price band — the same item can never be booked
    // twice (also enforced by a unique index). 0 = no cap: seats are limited only
    // by pool availability.
    //
    // ponytail: uncapped by product decision. A buyer who takes every seat wins
    // with certainty and has the other 99 advances refunded as wallet credit, so
    // the platform carries that credit as a liability. Set a number here to
    // reinstate a ceiling.
    'max_entries_per_user' => 0,
    'choice_window_days' => 7,    // non-winner has this long to pick pay-99% vs wallet credit
    'default_platform_fee_pct' => 8, // listed_price = vendor payout + this fee (global default)
];

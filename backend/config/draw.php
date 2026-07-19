<?php

// Central knobs for the two buying models. Change here, not scattered across code.
return [
    'batch_size' => 100,          // entries per club pool (odds 1 in 100)
    'entry_pct' => 1,             // % of listed price charged as the advance booking
    'wallet_cap_pct' => 1,        // max % of an item's price payable from wallet on a 100% buy
    'max_entries_per_user' => 1,  // per open club pool
    'choice_window_days' => 7,    // non-winner has this long to pick pay-99% vs wallet credit
    'default_platform_fee_pct' => 8, // listed_price = vendor payout + this fee (global default)
];

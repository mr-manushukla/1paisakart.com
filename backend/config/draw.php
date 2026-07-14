<?php

// Central knobs for the two buying models. Change here, not scattered across code.
return [
    'batch_size' => 100,          // entries per draw batch
    'entry_pct' => 1,             // % of listed price charged per draw entry
    'wallet_cap_pct' => 10,       // max % of an item's price payable from wallet on a 100% buy
    'max_entries_per_user' => 1,  // per open batch
    'default_platform_fee_pct' => 8, // listed_price = vendor payout + this fee (global default)
];

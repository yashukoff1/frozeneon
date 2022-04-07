<?php

namespace Model\Enum;
use App;
use System\Emerald\Emerald_enum;

class Transaction_info extends Emerald_enum {
    const BUY_BOOSTERPACK = 'buy_boosterpack';
    const ADD_MONEY_TO_WALLET = 'add_money_to_wallet';
}
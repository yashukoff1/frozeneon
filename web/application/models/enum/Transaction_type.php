<?php

namespace Model\Enum;
use App;
use System\Emerald\Emerald_enum;

class Transaction_type extends Emerald_enum {
    const INCOME = 'income';
    const EXPENSE = 'expense';
}
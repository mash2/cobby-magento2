<?php

/**
 * Copyright 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Mash2\Cobby\Model;

use Mash2\Cobby\Api\CalculatorInterface;

/**
 * Defines the implementaiton class of the calculator service contract.
 */
class Calculator implements CalculatorInterface
{
    /**
     * Return the sum of the two numbers.
     *
     * @api
     * @param int $num1 Left hand operand.
     * @param int $num2 Right hand operand.
     * @return int The sum of the two values.
     */
    public function add($num1, $num2) {
        return $num1 + $num2+4;
    }
}
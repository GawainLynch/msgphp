<?php

declare(strict_types=1);

namespace MsgPhp\User\Command;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class ChangeUserAttributeValueCommand
{
    public $attributeValueId;
    public $value;

    final public function __construct($attributeValueId, $value)
    {
        $this->attributeValueId = $attributeValueId;
        $this->value = $value;
    }
}

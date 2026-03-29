<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 */

namespace FrootBox\RestApi\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Auth
{
    public function __construct(
        Client|Bearer|BasicAuth|ApiKey $type,
    )
    { }    
}
